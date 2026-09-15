<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use App\Filament\Resources\TaskStatuses\TaskStatusResource;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    public function test_resource_index_pages_render(): void
    {
        // Neither keeps a dedicated Create/Edit page anymore — both open
        // as a modal instead (Project's Tasks relation manager still lives
        // on its View page), covered by ModalCreateEditTest. See
        // docs/filament-admin-layout-design.md §6.
        foreach ([ProjectResource::class, TaskStatusResource::class] as $resource) {
            $this->get($resource::getUrl('index', tenant: $this->company))->assertOk();
        }
    }

    /**
     * Phase 03 requirement (docs/rebuild/specs/03-sales-and-job/Specs.md):
     * "Keep generic legacy projects/tasks out of the launch navigation."
     * The resources/routes/data above stay fully intact for legacy data —
     * only the sidebar entry is hidden, once the SalesOrder/Job resource
     * (App\Filament\Resources\SalesOrders) supersedes them as the launch
     * "job" concept.
     */
    public function test_project_and_task_status_resources_are_hidden_from_navigation(): void
    {
        $this->assertFalse(ProjectResource::shouldRegisterNavigation());
        $this->assertFalse(TaskStatusResource::shouldRegisterNavigation());
    }

    public function test_tasks_relation_manager_can_start_and_stop_a_timer(): void
    {
        $project = Project::create(['company_id' => $this->company->id, 'name' => 'Website revamp']);
        $task = Task::create(['company_id' => $this->company->id, 'project_id' => $project->id, 'description' => 'Build homepage']);

        Livewire::test(TasksRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => ViewProject::class,
        ])->callTableAction('startTimer', $task);

        $this->assertTrue($task->fresh()->is_running);
        $this->assertNotNull($task->fresh()->started_at);

        Livewire::test(TasksRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => ViewProject::class,
        ])->callTableAction('stopTimer', $task);

        $this->assertFalse($task->fresh()->is_running);
        $this->assertNotNull($task->fresh()->stopped_at);
    }
}
