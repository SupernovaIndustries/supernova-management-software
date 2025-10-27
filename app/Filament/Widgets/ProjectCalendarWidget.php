<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Activity;
use Filament\Widgets\Widget;
use Carbon\Carbon;

class ProjectCalendarWidget extends Widget
{
    protected static string $view = 'filament.widgets.project-calendar-widget';
    
    protected int | string | array $columnSpan = 'full';

    public function getColumnSpan(): int | string | array
    {
        return 'full';
    }

    public $currentMonth;
    public $currentYear;

    public function mount()
    {
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
    }

    public function previousMonth()
    {
        $currentDate = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $previousMonth = $currentDate->subMonth();
        $this->currentMonth = $previousMonth->month;
        $this->currentYear = $previousMonth->year;
    }

    public function nextMonth()
    {
        $currentDate = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $nextMonth = $currentDate->addMonth();
        $this->currentMonth = $nextMonth->month;
        $this->currentYear = $nextMonth->year;
    }

    public function getCurrentMonthName()
    {
        return Carbon::create($this->currentYear, $this->currentMonth, 1)->format('F Y');
    }

    public function getCalendarDays()
    {
        $firstDayOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $lastDayOfMonth = $firstDayOfMonth->copy()->endOfMonth();
        
        // Start from the beginning of the week
        $startDate = $firstDayOfMonth->copy()->startOfWeek();
        $endDate = $lastDayOfMonth->copy()->endOfWeek();
        
        $days = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $days[] = [
                'date' => $currentDate->copy(),
                'day' => $currentDate->day,
                'is_current_month' => $currentDate->month == $this->currentMonth,
                'is_today' => $currentDate->isToday(),
                'events' => $this->getEventsForDate($currentDate),
            ];
            $currentDate->addDay();
        }
        
        return collect($days)->chunk(7);
    }

    public function getEventsForDate($date)
    {
        $events = [];
        
        // Project start dates
        $projectStarts = Project::whereDate('start_date', $date)->get();
        foreach ($projectStarts as $project) {
            $events[] = [
                'type' => 'project_start',
                'title' => 'Start: ' . $project->name,
                'project' => $project,
                'color' => 'bg-green-100 text-green-800',
            ];
        }
        
        // Project due dates
        $projectDues = Project::whereDate('due_date', $date)->get();
        foreach ($projectDues as $project) {
            $events[] = [
                'type' => 'project_due',
                'title' => 'Due: ' . $project->name,
                'project' => $project,
                'color' => $date->isPast() && $project->status !== 'completed' 
                    ? 'bg-red-100 text-red-800' 
                    : 'bg-blue-100 text-blue-800',
            ];
        }
        
        // Activities
        $activities = Activity::whereDate('scheduled_at', $date)->get();
        foreach ($activities as $activity) {
            $events[] = [
                'type' => 'activity',
                'title' => $activity->title,
                'activity' => $activity,
                'color' => 'bg-purple-100 text-purple-800',
            ];
        }
        
        return $events;
    }

    public function getDayClasses($day)
    {
        $classes = ['h-32', 'border', 'border-gray-200', 'p-2', 'overflow-y-auto'];
        
        if (!$day['is_current_month']) {
            $classes[] = 'bg-gray-50';
            $classes[] = 'text-gray-400';
        } else {
            $classes[] = 'bg-white';
        }
        
        if ($day['is_today']) {
            $classes[] = 'bg-blue-50';
            $classes[] = 'border-blue-300';
        }
        
        return implode(' ', $classes);
    }
}