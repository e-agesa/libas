<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'nullable|in:self,son,daughter,brother,sister,wife,husband,employee,other',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'age_group' => 'nullable|in:child,teen,adult,senior',
            'notes' => 'nullable|string|max:2000',
        ]);

        $contact = $client->contacts()->create($validated);

        if ($request->boolean('open_measurement')) {
            return redirect()->route('contacts.show', $contact)
                ->with('success', 'Contact created. Add a measurement now.');
        }

        return redirect()->route('clients.show', $client)
            ->with('success', 'Contact added successfully.');
    }

    public function show(Contact $contact)
    {
        $contact->load([
            'client',
            'measurements' => fn ($q) => $q->latest('date_taken'),
            'measurements.measuredByUser',
        ]);

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'nullable|in:self,son,daughter,brother,sister,wife,husband,employee,other',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'age_group' => 'nullable|in:child,teen,adult,senior',
            'notes' => 'nullable|string|max:2000',
        ]);

        $contact->update($validated);

        return redirect()->back()
            ->with('success', 'Contact updated successfully.');
    }

    public function destroy(Contact $contact)
    {
        $clientId = $contact->client_id;
        $contact->delete();

        return redirect()->route('clients.show', $clientId)
            ->with('success', 'Contact deleted successfully.');
    }
}
