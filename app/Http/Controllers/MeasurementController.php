<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\GarmentType;
use App\Models\Measurement;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public function store(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'garment_type' => 'required|string|exists:garment_types,slug',
            'label' => 'nullable|string|max:255',
            'date_taken' => 'required|date',
            'unit' => 'required|in:cm,inches',
            'values' => 'required|array',
            'values.*' => 'nullable|numeric|min:0|max:999',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['measured_by'] = $request->user()->id;

        $contact->measurements()->create($validated);

        return redirect()->route('contacts.show', $contact)
            ->with('success', 'Measurement saved successfully.');
    }

    public function update(Request $request, Measurement $measurement)
    {
        $validated = $request->validate([
            'garment_type' => 'required|string|exists:garment_types,slug',
            'label' => 'nullable|string|max:255',
            'date_taken' => 'required|date',
            'unit' => 'required|in:cm,inches',
            'values' => 'required|array',
            'values.*' => 'nullable|numeric|min:0|max:999',
            'notes' => 'nullable|string|max:2000',
        ]);

        $measurement->update($validated);

        return redirect()->route('contacts.show', $measurement->contact_id)
            ->with('success', 'Measurement updated successfully.');
    }

    public function destroy(Measurement $measurement)
    {
        $contactId = $measurement->contact_id;
        $measurement->delete();

        return redirect()->route('contacts.show', $contactId)
            ->with('success', 'Measurement deleted successfully.');
    }
}
