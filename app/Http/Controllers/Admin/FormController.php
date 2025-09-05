<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function index()
    {
        $forms = Form::withCount('leads')
            ->with(['leads' => function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.forms.index', compact('forms'));
    }

    public function create()
    {
        return view('admin.forms.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'header_text' => 'required|string',
            'sub_header_text' => 'required|string',
        ]);

        $form = Form::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'header_text' => $validated['header_text'],
            'sub_header_text' => $validated['sub_header_text'],
            'fields_config' => (new Form)->getDefaultFieldsConfig(),
            'products' => (new Form)->getDefaultProducts(),
            'payment_methods' => (new Form)->getDefaultPaymentMethods(),
            'delivery_options' => (new Form)->getDefaultDeliveryOptions(),
        ]);

        return redirect()->route('admin.forms.edit', $form)
            ->with('success', 'Form created successfully! Configure your form settings below.');
    }

    public function edit(Form $form)
    {
        return view('admin.forms.edit', compact('form'));
    }

    public function update(Request $request, Form $form)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'header_text' => 'required|string',
            'sub_header_text' => 'required|string',
            'thank_you_message' => 'required|string',
            'background_color' => 'required|string',
            'primary_color' => 'required|string',
            'fields_config' => 'required|array',
            'products' => 'required|array',
            'payment_methods' => 'required|array',
            'delivery_options' => 'required|array',
            'is_active' => 'boolean'
        ]);

        $form->update($validated);

        return redirect()->back()->with('success', 'Form updated successfully!');
    }

    public function destroy(Form $form)
    {
        $form->delete();
        return redirect()->route('admin.forms.index')
            ->with('success', 'Form deleted successfully!');
    }

    public function duplicate(Form $form)
    {
        $newForm = $form->replicate();
        $newForm->name = $form->name . ' (Copy)';
        $newForm->slug = Str::slug($newForm->name);
        $newForm->is_active = false;
        $newForm->total_submissions = 0;
        $newForm->last_submission_at = null;
        $newForm->save();

        return redirect()->route('admin.forms.edit', $newForm)
            ->with('success', 'Form duplicated successfully!');
    }

    public function preview(Form $form)
    {
        return view('forms.show', compact('form'));
    }

    public function embedCode(Form $form)
    {
        $embedUrl = route('forms.show', $form);
        $iframeCode = '<iframe src="' . $embedUrl . '" width="100%" height="800" frameborder="0"></iframe>';
        
        return response()->json([
            'iframe_code' => $iframeCode,
            'form_url' => $embedUrl,
            'form_id' => $form->id
        ]);
    }
} 