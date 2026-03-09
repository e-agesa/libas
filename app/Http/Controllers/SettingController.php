<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class SettingController extends Controller
{
    public function index()
    {
        $users = User::with('roles:id,name')
            ->select('id', 'name', 'email', 'phone', 'status', 'created_at')
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'role' => $user->roles->first()?->name ?? 'No role',
                'created_at' => $user->created_at->format('d M Y'),
            ]);

        $roles = Role::orderBy('name')->pluck('name');
        $company = CompanySetting::get();

        $shopCollections = Collection::select('id', 'name', 'sku', 'price', 'stock_qty', 'status', 'show_on_shop', 'image_path')
            ->with('category:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings/Index', [
            'users' => $users,
            'roles' => $roles,
            'company' => $company,
            'shopCollections' => $shopCollections,
        ]);
    }

    // ── Company Settings ──────────────────────────────────────────────────────

    public function updateCompany(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'tax_number' => 'nullable|string|max:100',
            'footer_text' => 'nullable|string|max:500',
        ]);

        CompanySetting::get()->update($validated);

        return redirect()->back()
            ->with('success', 'Business details updated successfully.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $company = CompanySetting::get();

        // Delete old logo
        if ($company->logo_path && Storage::exists($company->logo_path)) {
            Storage::delete($company->logo_path);
        }

        $path = $request->file('logo')->store('logos', 'public');
        $company->update(['logo_path' => 'public/' . $path]);

        return redirect()->back()
            ->with('success', 'Logo uploaded successfully.');
    }

    public function removeLogo()
    {
        $company = CompanySetting::get();

        if ($company->logo_path && Storage::exists($company->logo_path)) {
            Storage::delete($company->logo_path);
        }

        $company->update(['logo_path' => null]);

        return redirect()->back()
            ->with('success', 'Logo removed.');
    }

    // ── Website / Shop Settings ────────────────────────────────────────────────

    public function updateWebsite(Request $request)
    {
        $validated = $request->validate([
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:500',
            'hero_badge' => 'nullable|string|max:100',
            'whatsapp_number' => 'nullable|string|max:30',
            'instagram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'tiktok' => 'nullable|string|max:255',
            'about_text' => 'nullable|string|max:2000',
            'shop_enabled' => 'boolean',
        ]);

        CompanySetting::get()->update($validated);

        return redirect()->back()->with('success', 'Website settings updated.');
    }

    public function toggleShopItem(Request $request, Collection $collection)
    {
        $collection->update(['show_on_shop' => !$collection->show_on_shop]);

        return redirect()->back()->with('success',
            $collection->show_on_shop ? "{$collection->name} is now visible on shop." : "{$collection->name} hidden from shop."
        );
    }

    public function bulkToggleShop(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:collections,id',
            'show' => 'required|boolean',
        ]);

        Collection::whereIn('id', $validated['ids'])->update(['show_on_shop' => $validated['show']]);

        $action = $validated['show'] ? 'shown on' : 'hidden from';
        return redirect()->back()->with('success', count($validated['ids']) . " items {$action} shop.");
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', Rules\Password::defaults()],
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        $user->assignRole($validated['role']);

        return redirect()->back()
            ->with('success', 'User created successfully.');
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'password' => ['nullable', Rules\Password::defaults()],
            'role' => 'required|string|exists:roles,name',
            'status' => 'required|in:active,inactive',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'status' => $validated['status'],
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

        return redirect()->back()
            ->with('success', 'User updated successfully.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->back()
            ->with('success', 'User deleted successfully.');
    }
}
