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
            'parent_id' => 'nullable|exists:measurements,id',
        ]);

        $validated['measured_by'] = $request->user()->id;

        // If this is a revision, calculate the revision number
        if (!empty($validated['parent_id'])) {
            $parent = Measurement::findOrFail($validated['parent_id']);
            $rootId = $parent->parent_id ?? $parent->id;
            $validated['parent_id'] = $rootId;
            $validated['revision'] = (Measurement::where('parent_id', $rootId)->max('revision') ?? $parent->revision) + 1;
            $validated['garment_type'] = $parent->garment_type;
        } else {
            $validated['revision'] = 1;
        }

        $measurement = $contact->measurements()->create($validated);

        // Quick-add from the invoice wizard (axios): return the measurement
        // instead of redirecting so the in-progress invoice form is not lost.
        if ($request->wantsJson()) {
            return response()->json($measurement, 201);
        }

        return redirect()->route('contacts.show', $contact)
            ->with('success', 'Measurement saved successfully.');
    }

    /**
     * Full measurement as JSON — used by the invoice wizard's inline
     * "Edit Measurement" modal, which is only given id/garment_type/label
     * in its page props and needs the recorded values to pre-fill.
     */
    public function show(Measurement $measurement)
    {
        return response()->json(
            $measurement->only([
                'id', 'contact_id', 'garment_type', 'label',
                'date_taken', 'unit', 'values', 'notes', 'revision',
            ])
        );
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

        // Inline edit from the invoice wizard (axios): return the updated
        // record so the in-progress invoice is not lost to a redirect.
        if ($request->wantsJson()) {
            return response()->json($measurement->fresh());
        }

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
