<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PersonnelController extends Controller
{
    public function index(): View
    {
        return view('personnel.index', [
            'personnel' => Personnel::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('personnel.form', ['personnel' => new Personnel(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Personnel::query()->create($this->validated($request));

        return redirect()->route('personnel.index')->with('status', 'Personnel added. Phones receive the update on their next sync.');
    }

    public function edit(Personnel $personnel): View
    {
        return view('personnel.form', ['personnel' => $personnel]);
    }

    public function update(Request $request, Personnel $personnel): RedirectResponse
    {
        $personnel->update($this->validated($request, $personnel));

        return redirect()->route('personnel.index')->with('status', 'Personnel updated. Phones receive the update on their next sync.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Personnel $personnel = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('personnel', 'name')->ignore($personnel)],
            'position' => ['required', Rule::in(Personnel::POSITIONS)],
        ]);

        return [...$validated, 'is_active' => $request->boolean('is_active')];
    }
}
