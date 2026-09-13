<?php

namespace App\Livewire\Settings;

use App\Enums\ConfigCategory;
use App\Enums\ConfigDataType;
use App\Exports\SystemConfigurationsExport;
use App\Livewire\Traits\HasNotification;
use App\Models\SystemConfiguration as SystemConfigModel;
use App\Services\SystemConfigurationService;
use App\Traits\HasDynamicLike;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class SystemConfiguration extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, WithPagination;

    protected $paginationTheme = 'tailwind';

    public $search = '';

    public $isActiveFilter = '';

    public bool $filterChanged = false;

    public $showModal = false;

    public $editMode = false;

    // Form fields
    public $configId;

    public $key;

    public $category = 'general';

    public $value;

    public $data_type = 'string';

    public $description;

    public $is_editable = true;

    public $is_active = true;

    public function mount()
    {
        $this->authorize('viewAny', SystemConfigModel::class);
    }

    public function rules()
    {
        return [
            'key' => ['required', 'string', 'max:100', $this->editMode ? 'unique:system_configurations,key,'.$this->configId : 'unique:system_configurations,key'],
            'category' => ['required', 'string', Rule::in(ConfigCategory::values())],
            'value' => $this->data_type === 'datetime' ? 'nullable' : 'required',
            'data_type' => ['required', 'string', Rule::in(ConfigDataType::values())],
            'description' => 'nullable|string',
            'is_editable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingIsActiveFilter()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function resetFilters()
    {
        $this->isActiveFilter = '';
        $this->resetPage();
        $this->filterChanged = true;
        $this->notifySuccess('Filter berhasil direset.');
    }

    public function getIsActiveOptionsProperty(): array
    {
        return [
            ['value' => '1', 'label' => 'Aktif'],
            ['value' => '0', 'label' => 'Nonaktif'],
        ];
    }

    public function edit($id)
    {
        $config = SystemConfigModel::findOrFail($id);
        $this->authorize('update', $config);

        $this->configId = $config->id;
        $this->key = $config->key;
        $this->category = $config->category instanceof ConfigCategory ? $config->category->value : $config->category;

        $dataType = $config->data_type instanceof ConfigDataType ? $config->data_type->value : $config->data_type;

        if ($dataType === 'datetime' && ! empty($config->value)) {
            try {
                $this->value = \Carbon\Carbon::parse($config->value)->format('Y-m-d\TH:i');
            } catch (\Exception $e) {
                $this->value = $config->value;
            }
        } else {
            $this->value = $config->value;
        }

        $this->data_type = $dataType;
        $this->description = $config->description;
        $this->is_editable = $config->is_editable;
        $this->is_active = $config->is_active;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(SystemConfigurationService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $value = $this->value;

            if ($this->data_type === 'datetime' && ! empty($value)) {
                try {
                    $value = \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // Keep original value if parsing fails
                }
            }

            $data = [
                'key' => $this->key,
                'category' => $this->category,
                'value' => $value,
                'data_type' => $this->data_type,
                'description' => $this->description,
                'is_editable' => $this->is_editable,
                'is_active' => $this->is_active,
            ];

            $config = SystemConfigModel::findOrFail($this->configId);
            $this->authorize('update', $config);

            $service->update($config, $data);
            $message = 'Konfigurasi berhasil diupdate!';

            $this->notifySuccess($message);
            $this->closeModal();
            $this->resetForm();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function toggleActive($id, SystemConfigurationService $service)
    {
        try {
            $config = SystemConfigModel::findOrFail($id);
            $this->authorize('toggleActive', $config);

            $service->toggleActive($config);

            $status = $config->is_active ? 'diaktifkan' : 'dinonaktifkan';
            $this->notifySuccess("Konfigurasi berhasil {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status konfigurasi.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset([
            'configId',
            'key',
            'category',
            'value',
            'data_type',
            'description',
            'is_editable',
            'is_active',
        ]);

        $this->category = ConfigCategory::General->value;
        $this->data_type = ConfigDataType::String->value;
        $this->is_editable = true;
        $this->is_active = true;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', SystemConfigModel::class);

        return (new SystemConfigurationsExport($this->search, $this->isActiveFilter !== '' ? $this->isActiveFilter : null))
            ->download('konfigurasi-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf(SystemConfigurationService $service)
    {
        $this->authorize('exportPdf', SystemConfigModel::class);

        $query = SystemConfigModel::query();

        if ($this->search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($operator) {
                $q->where('key', $operator, "%{$this->search}%")
                    ->orWhere('description', $operator, "%{$this->search}%")
                    ->orWhere('value', $operator, "%{$this->search}%");
            });
        }

        if ($this->isActiveFilter !== null && $this->isActiveFilter !== '') {
            $query->where('is_active', $this->isActiveFilter === '1');
        }

        $configurations = $query->orderBy('category')->orderBy('key')->get();

        $pdf = Pdf::loadView('exports.configurations-pdf', ['configurations' => $configurations]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'konfigurasi-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    public function render(SystemConfigurationService $service)
    {
        $configurations = $service->getFiltered($this->search, $this->isActiveFilter !== '' ? $this->isActiveFilter : null);

        if ($this->filterChanged) {
            $this->notifySuccess("Ditemukan {$configurations->total()} data konfigurasi.");
            $this->filterChanged = false;
        }

        return view('livewire.settings.system-configuration', [
            'configurations' => $configurations,
            'categories' => ConfigCategory::options(),
            'dataTypes' => ConfigDataType::options(),
        ]);
    }
}
