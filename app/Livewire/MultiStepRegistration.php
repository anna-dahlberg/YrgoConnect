<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\User;
use App\Models\Role;
use App\Models\YrgoClass;
use App\Models\Company;
use App\Models\Student;
use App\Models\Competence;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;


class MultiStepRegistration extends Component
{
    use WithFileUploads;

    public $step = 1;
    public $user_id;

    // User-data
    public $email, $password, $password_confirmation, $role;

    // Company-data
    public $company_name, $image, $city, $contact_name, $website_url;
    public $class = [], $competences = [], $description, $cv, $linkedin_url, $name, $class_id;
    public $event_attendance = false, $accept_terms = false;

    public $availableCompetences = [];

    protected $rules = [
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8|confirmed',
        'role' => 'required|in:student,company',
    ];

    public function updatedClass()
    {
        if ($this->class) {
            $classId = $this->getClassIdFromName($this->class);
            $this->availableCompetences = Competence::where('class_id', $classId)->pluck('name')->toArray();
        }
    }

    private function getClassIdFromName($name)
    {
        return match ($name) {
            'Webbutvecklare' => 1,
            'Digital Designer' => 2,
            default => null,
        };
    }

    public function registerUser()
    {
        $validated = $this->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|same:password_confirmation',
            'role' => 'required|in:student,company',
        ]);

        $roleName = $this->role === 'student' ? 'Student' : 'Företag';
        $roleId = Role::where('name', $roleName)->value('id');

        if (!$roleId) {
            $this->addError('role', 'Ogiltig roll vald');
            return;
        }

        $user = User::create([
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role_id' => $roleId,
        ]);

        Auth::login($user);
        $this->user_id = $user->id;
        $this->step++;
    }

    public function nextStep()
    {
        $this->step++;
    }

    public function previousStep()
    {
        $this->step--;
    }

    public function submit()
    {

        if ($this->role === 'company') {

            $this->validate([
                'company_name' => 'required|string',
                'image' => 'nullable|image|max:2048',
                'city' => 'nullable|string',
                'contact_name' => 'required|string',
                'website_url' => 'nullable|url',
                'class' => 'required|array',
                'competences' => 'nullable|array',
                'description' => 'nullable|string|max:240',
                'event_attendance' => 'boolean',
                'accept_terms' => 'accepted',
            ]);

            $imagePath = $this->image ? $this->image->store('logos', 'public') : null;

            $company = Company::create([
                'user_id' => $this->user_id,
                'name' => $this->company_name,
                'image_url' => $imagePath,
                'city' => $this->city,
                'contact_name' => $this->contact_name,
                'website_url' => $this->website_url,
                'description' => $this->description,
                'attendance' => $this->event_attendance,

            ]);

            if (!empty($this->class)) {
                $classIds = YrgoClass::whereIn('name', $this->class)->pluck('id');
                $company->classes()->sync($classIds);
            }

            if (!empty($this->competences)) {
                $competenceIds = Competence::whereIn('name', $this->competences)->pluck('id');
                $company->competences()->sync($competenceIds);
            };
        }
        if ($this->role === 'student') {

            $this->validate([
                'name' => 'required|string|max:255',
                'image' => 'nullable|image|max:1024',
                'website_url' => 'required|url',
                'class' => 'required|in:Webbutvecklare,Digital Designer',
                'competences' => 'nullable|array',
                'competences.*' => 'string',
                'description' => 'nullable|string|max:240',
                'cv' => 'nullable|file|mimes:pdf|max:2048',
                'linkedin_url' => 'nullable|url',
                'accept_terms' => 'accepted',
            ]);

            $imagePath = $this->image ? $this->image->store('profiles', 'public') : null;
            $cvPath = $this->cv ? $this->cv->store('cv', 'public') : null;
            $classId = $this->getClassIdFromName($this->class);

            $student = Student::create([
                'user_id' => $this->user_id,
                'name' => $this->name,
                'image_url' => $imagePath,
                'website_url' => $this->website_url,
                'description' => $this->description,
                'cv_url' => $cvPath,
                'linkedin_url' => $this->linkedin_url,
                'class_id' => $classId,
            ]);

            if (!empty($this->competences)) {
                $competenceIds = Competence::whereIn('name', $this->competences)->pluck('id');
                $student->competences()->sync($competenceIds);
            }
        }

        $this->step = 5;
    }

    public function render()
    {
        return view('livewire.multi-step-registration');
    }
}
