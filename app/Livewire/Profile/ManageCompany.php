<?php

namespace App\Livewire\Profile;

use App\Livewire\Traits\HasNotification;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ManageCompany extends Component
{
    use HasNotification;

    public $showModal = false;

    public $isEditMode = false;

    // Form fields
    public $code;

    public $name;

    public $email;

    public $phone;

    public $address;

    public $pic_name;

    public $pic_email;

    public $pic_phone;

    public $npwp;

    protected function rules()
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                $this->isEditMode ? 'unique:companies,code,'.$companyId : 'unique:companies,code',
            ],
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'pic_name' => 'nullable|string|max:255',
            'pic_email' => 'nullable|email|max:255',
            'pic_phone' => 'nullable|string|max:20',
            'npwp' => [
                'required',
                'string',
                'regex:/^[0-9]{15,16}$/',
                $this->isEditMode ? 'unique:companies,npwp,'.$companyId : 'unique:companies,npwp',
            ],
        ];
    }

    protected $messages = [
        'code.required' => 'Kode perusahaan wajib diisi',
        'code.unique' => 'Kode perusahaan sudah digunakan',
        'name.required' => 'Nama perusahaan wajib diisi',
        'email.email' => 'Format email tidak valid',
        'pic_email.email' => 'Format email PIC tidak valid',
        'npwp.required' => 'NPWP wajib diisi',
        'npwp.regex' => 'NPWP harus 15 digit (format lama) atau 16 digit (format baru)',
        'npwp.unique' => 'NPWP sudah terdaftar untuk perusahaan lain',
    ];

    public function openModal()
    {
        $user = Auth::user();

        if (! $user->can('manage_own_company')) {
            $this->notifyError('Anda tidak memiliki izin untuk mengelola perusahaan');

            return;
        }

        if ($user->company_id && $user->company) {
            // Edit mode - load existing company data
            $this->isEditMode = true;
            $company = $user->company;

            $this->code = $company->code;
            $this->name = $company->name;
            $this->email = $company->email;
            $this->phone = $company->phone;
            $this->address = $company->address;
            $this->pic_name = $company->pic_name;
            $this->pic_email = $company->pic_email;
            $this->pic_phone = $company->pic_phone;
            $this->npwp = $company->npwp;
        } else {
            // Create mode
            $this->isEditMode = false;
            $this->resetForm();
        }

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetErrorBag();
    }

    public function save()
    {
        if (! Auth::user()->can('manage_own_company')) {
            $this->notifyError('Anda tidak memiliki izin untuk mengelola perusahaan');

            return;
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $user = Auth::user();

            if ($this->isEditMode) {
                // Update existing company
                $company = $user->company;
                $company->update([
                    'code' => $this->code,
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'pic_name' => $this->pic_name,
                    'pic_email' => $this->pic_email,
                    'pic_phone' => $this->pic_phone,
                    'npwp' => $this->npwp,
                ]);

                $message = 'Informasi perusahaan berhasil diperbarui!';
            } else {
                // Create new company and assign to user
                $company = Company::create([
                    'code' => $this->code,
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'pic_name' => $this->pic_name,
                    'pic_email' => $this->pic_email,
                    'pic_phone' => $this->pic_phone,
                    'npwp' => $this->npwp,
                    'status' => 'active',
                ]);

                // Assign company to user
                $user->company_id = $company->id;
                $user->save();

                $message = 'Perusahaan berhasil dibuat dan dihubungkan ke akun Anda!';
            }

            $this->notifySuccess($message);

            $this->closeModal();

            // Refresh the page to show updated company info
            return $this->redirect(route('profile'), navigate: true);

        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    private function resetForm()
    {
        $this->reset([
            'code',
            'name',
            'email',
            'phone',
            'address',
            'pic_name',
            'pic_email',
            'pic_phone',
            'npwp',
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $hasCompany = $user->company_id && $user->company;
        $canManage = $user->can('manage_own_company');

        return view('livewire.profile.manage-company', [
            'hasCompany' => $hasCompany,
            'canManage' => $canManage,
            'company' => $hasCompany ? $user->company : null,
        ]);
    }
}
