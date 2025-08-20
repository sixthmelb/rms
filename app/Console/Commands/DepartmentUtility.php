<?php
// app/Console/Commands/DepartmentUtility.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Department;
use App\Models\Company;
use App\Models\User;

class DepartmentUtility extends Command
{
    protected $signature = 'department:utility {action} {--company=} {--department=}';
    protected $description = 'Utility commands for department management';

    public function handle()
    {
        $action = $this->argument('action');

        match ($action) {
            'list' => $this->listDepartments(),
            'create' => $this->createDepartment(),
            'assign-section-head' => $this->assignSectionHead(),
            'check-missing-heads' => $this->checkMissingSectionHeads(),
            'stats' => $this->showStats(),
            'cleanup' => $this->cleanup(),
            default => $this->error("Unknown action: {$action}")
        };

        return 0;
    }

    private function listDepartments()
    {
        $companyId = $this->option('company');
        
        $query = Department::with(['company', 'users']);
        
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        
        $departments = $query->get();
        
        $this->info('📋 Department List:');
        $this->table(
            ['ID', 'Company', 'Name', 'Code', 'Users', 'Section Head', 'Active Requests'],
            $departments->map(function ($dept) {
                return [
                    $dept->id,
                    $dept->company->code ?? 'N/A',
                    $dept->name,
                    $dept->code,
                    $dept->users()->count(),
                    $dept->getSectionHead()?->name ?? '❌ Not assigned',
                    $dept->getActiveRequestsCount(),
                ];
            })
        );
    }

    private function createDepartment()
    {
        $companies = Company::active()->pluck('name', 'id');
        
        if ($companies->isEmpty()) {
            $this->error('No active companies found. Please create companies first.');
            return;
        }
        
        $this->info('🏢 Available Companies:');
        foreach ($companies as $id => $name) {
            $this->line("  {$id}. {$name}");
        }
        
        $companyId = $this->ask('Enter Company ID');
        $name = $this->ask('Department Name');
        $code = $this->ask('Department Code (e.g., ENG, HR)');
        
        $company = Company::find($companyId);
        if (!$company) {
            $this->error('Company not found!');
            return;
        }
        
        $department = Department::create([
            'company_id' => $companyId,
            'name' => $name,
            'code' => strtoupper($code),
        ]);
        
        $this->info("✅ Department '{$department->name}' created successfully!");
        $this->line("   Company: {$company->name}");
        $this->line("   Code: {$department->code}");
    }

    private function assignSectionHead()
    {
        $departmentId = $this->option('department') ?? $this->ask('Department ID');
        
        $department = Department::with(['users', 'company'])->find($departmentId);
        
        if (!$department) {
            $this->error('Department not found!');
            return;
        }
        
        $this->info("🏢 Department: {$department->name} ({$department->company->name})");
        
        $existingHead = $department->getSectionHead();
        if ($existingHead) {
            $this->warn("Current Section Head: {$existingHead->name}");
            if (!$this->confirm('Replace current section head?')) {
                return;
            }
        }
        
        $availableUsers = $department->users()
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'section_head');
            })
            ->get();
            
        if ($availableUsers->isEmpty()) {
            $this->error('No available users in this department!');
            return;
        }
        
        $this->info('👥 Available Users:');
        foreach ($availableUsers as $user) {
            $this->line("  {$user->id}. {$user->name} ({$user->employee_id})");
        }
        
        $userId = $this->ask('Enter User ID to assign as Section Head');
        $user = $availableUsers->find($userId);
        
        if (!$user) {
            $this->error('User not found or not available!');
            return;
        }
        
        if ($department->assignSectionHead($user)) {
            $this->info("✅ {$user->name} assigned as Section Head for {$department->name}");
        } else {
            $this->error('Failed to assign section head!');
        }
    }

    private function checkMissingSectionHeads()
    {
        $departmentsWithoutHead = Department::withoutSectionHead()
            ->with(['company'])
            ->get();
            
        if ($departmentsWithoutHead->isEmpty()) {
            $this->info('✅ All departments have section heads assigned!');
            return;
        }
        
        $this->warn("⚠️  {$departmentsWithoutHead->count()} departments missing section heads:");
        
        $this->table(
            ['ID', 'Company', 'Department', 'Users Count'],
            $departmentsWithoutHead->map(function ($dept) {
                return [
                    $dept->id,
                    $dept->company->code,
                    "{$dept->name} ({$dept->code})",
                    $dept->users()->count(),
                ];
            })
        );
        
        if ($this->confirm('Auto-assign section heads where possible?')) {
            $this->autoAssignSectionHeads($departmentsWithoutHead);
        }
    }

    private function autoAssignSectionHeads($departments)
    {
        $assigned = 0;
        
        foreach ($departments as $department) {
            $availableUsers = $department->users()
                ->whereDoesntHave('roles', function ($q) {
                    $q->whereIn('name', ['section_head', 'scm_head', 'pjo', 'admin']);
                })
                ->get();
                
            if ($availableUsers->count() === 1) {
                $user = $availableUsers->first();
                if ($department->assignSectionHead($user)) {
                    $this->info("✅ Auto-assigned {$user->name} to {$department->name}");
                    $assigned++;
                }
            } elseif ($availableUsers->count() > 1) {
                $this->warn("⚠️  Multiple candidates for {$department->name}, manual assignment needed");
            }
        }
        
        $this->info("🎯 Auto-assigned {$assigned} section heads");
    }

    private function showStats()
    {
        $total = Department::count();
        $withSectionHead = Department::withSectionHead()->count();
        $withoutSectionHead = $total - $withSectionHead;
        $activeRequests = Department::whereHas('requests', function ($q) {
            $q->whereNotIn('status', ['completed', 'rejected']);
        })->count();
        
        $this->info('📊 Department Statistics:');
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Departments', $total, '100%'],
                ['With Section Head', $withSectionHead, round(($withSectionHead/$total)*100, 1) . '%'],
                ['Without Section Head', $withoutSectionHead, round(($withoutSectionHead/$total)*100, 1) . '%'],
                ['With Active Requests', $activeRequests, round(($activeRequests/$total)*100, 1) . '%'],
            ]
        );
        
        // Per company breakdown
        $this->info("\n🏢 Per Company Breakdown:");
        $companies = Company::withCount(['departments', 'users'])->get();
        
        $this->table(
            ['Company', 'Departments', 'Users', 'Avg Users/Dept'],
            $companies->map(function ($company) {
                $avgUsers = $company->departments_count > 0 
                    ? round($company->users_count / $company->departments_count, 1)
                    : 0;
                    
                return [
                    $company->name,
                    $company->departments_count,
                    $company->users_count,
                    $avgUsers,
                ];
            })
        );
    }

    private function cleanup()
    {
        $this->info('🧹 Department Cleanup:');
        
        // Find empty departments
        $emptyDepartments = Department::whereDoesntHave('users')
            ->whereDoesntHave('requests')
            ->with('company')
            ->get();
            
        if ($emptyDepartments->isEmpty()) {
            $this->info('✅ No empty departments found!');
            return;
        }
        
        $this->warn("Found {$emptyDepartments->count()} empty departments:");
        
        $this->table(
            ['ID', 'Company', 'Name', 'Code'],
            $emptyDepartments->map(function ($dept) {
                return [
                    $dept->id,
                    $dept->company->code,
                    $dept->name,
                    $dept->code,
                ];
            })
        );
        
        if ($this->confirm('Delete these empty departments?')) {
            $deleted = 0;
            foreach ($emptyDepartments as $department) {
                if ($department->canBeDeleted()) {
                    $department->delete();
                    $this->info("🗑️  Deleted: {$department->name}");
                    $deleted++;
                }
            }
            
            $this->info("🎯 Deleted {$deleted} empty departments");
        }
    }
}