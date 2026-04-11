<?php

namespace App\Http\Controllers;

use App\Models\GarmentType;
use App\Models\GarmentTypeField;
use App\Models\Measurement;
use Illuminate\Http\Request;

class GarmentTypeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'base_price' => 'nullable|numeric|min:0',
            'default_fabric_qty' => 'nullable|numeric|min:0',
        ]);

        $validated['slug'] = GarmentType::generateSlug($validated['name']);
        $validated['sort_order'] = GarmentType::max('sort_order') + 1;

        GarmentType::create($validated);

        return redirect()->back()
            ->with('success', 'Garment type created successfully.');
    }

    public function update(Request $request, GarmentType $garmentType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'is_active' => 'required|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'base_price' => 'nullable|numeric|min:0',
            'default_fabric_qty' => 'nullable|numeric|min:0',
        ]);

        $garmentType->update($validated);

        return redirect()->back()
            ->with('success', 'Garment type updated successfully.');
    }

    public function destroy(GarmentType $garmentType)
    {
        $usageCount = Measurement::where('garment_type', $garmentType->slug)->count();

        if ($usageCount > 0) {
            return redirect()->back()
                ->with('error', "Cannot delete: {$usageCount} measurement(s) use this garment type. Deactivate it instead.");
        }

        $garmentType->delete();

        return redirect()->back()
            ->with('success', 'Garment type deleted successfully.');
    }

    public function storeField(Request $request, GarmentType $garmentType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $slug = GarmentTypeField::generateSlug($validated['name']);

        if ($garmentType->fields()->where('slug', $slug)->exists()) {
            return redirect()->back()
                ->with('error', 'A field with this name already exists.');
        }

        $garmentType->fields()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => $garmentType->fields()->max('sort_order') + 1,
        ]);

        return redirect()->back()
            ->with('success', 'Field added successfully.');
    }

    public function updateField(Request $request, GarmentTypeField $field)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'sort_order' => 'sometimes|required|integer|min:0',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = GarmentTypeField::generateSlug($validated['name']);
        }

        $field->update($validated);

        return redirect()->back()
            ->with('success', 'Field updated successfully.');
    }

    public function destroyField(GarmentTypeField $field)
    {
        $field->delete();

        return redirect()->back()
            ->with('success', 'Field deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|exists:garment_types,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            GarmentType::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return redirect()->back()
            ->with('success', 'Order updated.');
    }

    public function reorderFields(Request $request, GarmentType $garmentType)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|exists:garment_type_fields,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            GarmentTypeField::where('id', $id)
                ->where('garment_type_id', $garmentType->id)
                ->update(['sort_order' => $index + 1]);
        }

        return redirect()->back()
            ->with('success', 'Field order updated.');
    }
}
