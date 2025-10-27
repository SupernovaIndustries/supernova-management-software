<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Cache;

class ImportProgress extends Component
{
    public string $jobId;
    public array $progress = [];

    public function mount(string $jobId)
    {
        $this->jobId = $jobId;
        $this->loadProgress();
    }

    public function loadProgress()
    {
        $this->progress = Cache::get("import_progress_{$this->jobId}", [
            'status' => 'unknown',
            'current' => 0,
            'total' => 0,
            'percentage' => 0,
            'message' => 'Caricamento...'
        ]);
    }

    public function render()
    {
        return view('livewire.import-progress');
    }
}
