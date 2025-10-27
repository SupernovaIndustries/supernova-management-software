<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectBom;
use App\Models\ProjectBomItem;
use App\Models\ProjectPcbFile;
use App\Models\Component;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class InteractiveBomService
{
    /**
     * Generate interactive BOM HTML for a project
     */
    public function generateInteractiveBom(Project $project, ProjectBom $bom): string
    {
        $bomItems = $bom->items()->with(['component'])->get();
        $pcbFiles = $project->pcbFiles()->where('format', 'gerber')->latest()->first();
        
        // Group components by reference
        $groupedComponents = $this->groupComponentsByReference($bomItems);
        
        // Generate HTML
        return $this->generateHtml($project, $bom, $groupedComponents, $pcbFiles);
    }
    
    /**
     * Group components by reference designator
     */
    private function groupComponentsByReference(Collection $bomItems): array
    {
        $grouped = [];
        
        foreach ($bomItems as $item) {
            $key = $item->component->manufacturer_part_number ?? $item->component->sku;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'component' => $item->component,
                    'quantity' => 0,
                    'references' => [],
                    'unit_price' => $item->unit_price,
                    'status' => $item->status,
                    'sourced' => $item->sourced,
                    'notes' => $item->notes,
                ];
            }
            
            $grouped[$key]['quantity'] += $item->quantity;
            
            // Parse reference designators (e.g., R1, R2, C1, C2)
            if ($item->reference_designator) {
                $refs = explode(',', str_replace(' ', '', $item->reference_designator));
                $grouped[$key]['references'] = array_merge($grouped[$key]['references'], $refs);
            }
        }
        
        return $grouped;
    }
    
    /**
     * Generate interactive BOM HTML
     */
    private function generateHtml(Project $project, ProjectBom $bom, array $groupedComponents, ?ProjectPcbFile $pcbFile): string
    {
        $totalComponents = array_sum(array_column($groupedComponents, 'quantity'));
        $totalCost = 0;
        $sourcedCount = 0;
        $placedCount = 0;
        
        foreach ($groupedComponents as $group) {
            $totalCost += $group['quantity'] * $group['unit_price'];
            if ($group['sourced']) {
                $sourcedCount += $group['quantity'];
            }
            if ($group['status'] === 'placed') {
                $placedCount += $group['quantity'];
            }
        }
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive BOM - ' . e($project->name) . '</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .header h1 { font-size: 24px; margin-bottom: 10px; }
        .header .info { color: #666; font-size: 14px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #f59e0b; }
        .stat-card .label { color: #666; font-size: 14px; margin-top: 5px; }
        .main-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        .component-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .component-list-header {
            background: #f59e0b;
            color: white;
            padding: 15px;
            font-weight: bold;
        }
        .search-box {
            padding: 15px;
            border-bottom: 1px solid #e5e5e5;
        }
        .search-box input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .component-table {
            width: 100%;
            border-collapse: collapse;
        }
        .component-table th {
            background: #f9f9f9;
            padding: 10px;
            text-align: left;
            font-size: 12px;
            color: #666;
            border-bottom: 2px solid #e5e5e5;
            position: sticky;
            top: 0;
        }
        .component-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e5e5;
            font-size: 14px;
        }
        .component-table tr:hover {
            background: #f9f9f9;
            cursor: pointer;
        }
        .component-table tr.selected {
            background: #fef3c7;
        }
        .pcb-viewer {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            min-height: 600px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pcb-placeholder {
            text-align: center;
            color: #999;
        }
        .pcb-placeholder svg { width: 100px; height: 100px; margin-bottom: 20px; }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-placed { background: #d1fae5; color: #065f46; }
        .status-missing { background: #fee2e2; color: #991b1b; }
        .status-sourced { background: #dbeafe; color: #1e40af; }
        .filters {
            padding: 15px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            gap: 10px;
        }
        .filter-btn {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-btn:hover { background: #f9f9f9; }
        .filter-btn.active {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }
        .component-details {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 400px;
            display: none;
        }
        .component-details.show { display: block; }
        .component-details h3 { margin-bottom: 15px; }
        .component-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .component-details .detail-label { color: #666; }
        .component-details .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            color: #999;
        }
        @media (max-width: 768px) {
            .main-content { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . e($project->name) . ' - Interactive BOM</h1>
            <div class="info">
                Version: ' . e($bom->version) . ' | 
                Created: ' . $bom->created_at->format('Y-m-d H:i') . ' | 
                ' . e($project->customer->name) . '
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="value">' . $totalComponents . '</div>
                <div class="label">Total Components</div>
            </div>
            <div class="stat-card">
                <div class="value">' . count($groupedComponents) . '</div>
                <div class="label">Unique Parts</div>
            </div>
            <div class="stat-card">
                <div class="value">' . round(($sourcedCount / $totalComponents) * 100) . '%</div>
                <div class="label">Sourced</div>
            </div>
            <div class="stat-card">
                <div class="value">€' . number_format($totalCost, 2) . '</div>
                <div class="label">Total Cost</div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="component-list">
                <div class="component-list-header">Component List</div>
                <div class="search-box">
                    <input type="text" id="search" placeholder="Search components..." />
                </div>
                <div class="filters">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="placed">Placed</button>
                    <button class="filter-btn" data-filter="sourced">Sourced</button>
                    <button class="filter-btn" data-filter="missing">Missing</button>
                </div>
                <div style="overflow-y: auto; max-height: 600px;">
                    <table class="component-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Part Number</th>
                                <th>Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="component-tbody">';
        
        foreach ($groupedComponents as $key => $group) {
            $references = implode(', ', array_slice($group['references'], 0, 3));
            if (count($group['references']) > 3) {
                $references .= ', ...';
            }
            
            $status = $group['status'] ?? 'missing';
            if ($group['sourced'] && $status !== 'placed') {
                $status = 'sourced';
            }
            
            $html .= '
                <tr class="component-row" data-component="' . e($key) . '" data-status="' . $status . '">
                    <td>' . e($references) . '</td>
                    <td>' . e($group['component']->manufacturer_part_number) . '</td>
                    <td>' . $group['quantity'] . '</td>
                    <td><span class="status-badge status-' . $status . '">' . ucfirst($status) . '</span></td>
                </tr>';
        }
        
        $html .= '
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="pcb-viewer">
                <div class="pcb-placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.5"/>
                        <circle cx="8" cy="8" r="1" fill="currentColor"/>
                        <circle cx="16" cy="8" r="1" fill="currentColor"/>
                        <circle cx="8" cy="16" r="1" fill="currentColor"/>
                        <circle cx="16" cy="16" r="1" fill="currentColor"/>
                        <rect x="10" y="10" width="4" height="4" fill="currentColor"/>
                    </svg>
                    <h3>PCB Viewer</h3>
                    <p>PCB visualization would appear here when integrated with Gerber viewer</p>
                </div>
            </div>
        </div>
        
        <div class="component-details" id="component-details">
            <span class="close-btn" onclick="closeDetails()">×</span>
            <h3 id="detail-title">Component Details</h3>
            <div id="detail-content"></div>
        </div>
    </div>
    
    <script>
        const components = ' . json_encode($groupedComponents) . ';
        let selectedComponent = null;
        let currentFilter = "all";
        
        // Search functionality
        document.getElementById("search").addEventListener("input", function(e) {
            filterComponents();
        });
        
        // Filter buttons
        document.querySelectorAll(".filter-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                document.querySelectorAll(".filter-btn").forEach(b => b.classList.remove("active"));
                this.classList.add("active");
                currentFilter = this.dataset.filter;
                filterComponents();
            });
        });
        
        // Component row clicks
        document.querySelectorAll(".component-row").forEach(row => {
            row.addEventListener("click", function() {
                document.querySelectorAll(".component-row").forEach(r => r.classList.remove("selected"));
                this.classList.add("selected");
                showComponentDetails(this.dataset.component);
            });
        });
        
        function filterComponents() {
            const searchTerm = document.getElementById("search").value.toLowerCase();
            const rows = document.querySelectorAll(".component-row");
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.dataset.status;
                const matchesSearch = text.includes(searchTerm);
                const matchesFilter = currentFilter === "all" || status === currentFilter;
                
                row.style.display = matchesSearch && matchesFilter ? "" : "none";
            });
        }
        
        function showComponentDetails(componentKey) {
            const component = components[componentKey];
            if (!component) return;
            
            selectedComponent = component;
            const detailsEl = document.getElementById("component-details");
            const contentEl = document.getElementById("detail-content");
            
            let html = `
                <div class="detail-row">
                    <span class="detail-label">Part Number:</span>
                    <span>${component.component.manufacturer_part_number || "N/A"}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Manufacturer:</span>
                    <span>${component.component.manufacturer || "N/A"}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Description:</span>
                    <span>${component.component.description || "N/A"}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Package:</span>
                    <span>${component.component.package || "N/A"}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Quantity:</span>
                    <span>${component.quantity}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Unit Price:</span>
                    <span>€${component.unit_price.toFixed(4)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Price:</span>
                    <span>€${(component.quantity * component.unit_price).toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">References:</span>
                    <span>${component.references.join(", ")}</span>
                </div>
            `;
            
            if (component.notes) {
                html += `
                <div class="detail-row">
                    <span class="detail-label">Notes:</span>
                    <span>${component.notes}</span>
                </div>`;
            }
            
            contentEl.innerHTML = html;
            document.getElementById("detail-title").textContent = component.component.name || "Component Details";
            detailsEl.classList.add("show");
        }
        
        function closeDetails() {
            document.getElementById("component-details").classList.remove("show");
            document.querySelectorAll(".component-row").forEach(r => r.classList.remove("selected"));
            selectedComponent = null;
        }
    </script>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Save interactive BOM to storage
     */
    public function saveInteractiveBom(Project $project, ProjectBom $bom): string
    {
        $html = $this->generateInteractiveBom($project, $bom);
        $filename = 'ibom/' . $project->id . '/ibom-v' . $bom->version . '-' . time() . '.html';
        
        Storage::disk('public')->put($filename, $html);
        
        return $filename;
    }
}