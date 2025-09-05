@extends('layouts.admin')

@section('title', 'Edit Form: ' . $form->name)

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- Left Sidebar -->
        <div class="col-lg-3">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Form Settings</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#basic-settings" class="list-group-item list-group-item-action active" data-section="basic-settings">
                        <i class="fas fa-cog me-2"></i>Basic Settings
                    </a>
                    <a href="#form-fields" class="list-group-item list-group-item-action" data-section="form-fields">
                        <i class="fas fa-wpforms me-2"></i>Form Fields
                    </a>
                    <a href="#products" class="list-group-item list-group-item-action" data-section="products">
                        <i class="fas fa-box me-2"></i>Products
                    </a>
                    <a href="#payment-methods" class="list-group-item list-group-item-action" data-section="payment-methods">
                        <i class="fas fa-credit-card me-2"></i>Payment Methods
                    </a>
                    <a href="#delivery-options" class="list-group-item list-group-item-action" data-section="delivery-options">
                        <i class="fas fa-truck me-2"></i>Delivery Options
                    </a>
                    <a href="#design" class="list-group-item list-group-item-action" data-section="design">
                        <i class="fas fa-palette me-2"></i>Design
                    </a>
                    <a href="#integration" class="list-group-item list-group-item-action" data-section="integration">
                        <i class="fas fa-code me-2"></i>Integration
                    </a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mt-3">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('forms.show', $form) }}" target="_blank" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-eye me-2"></i>Preview Form
                        </a>
                        <button class="btn btn-outline-primary btn-sm" onclick="copyEmbedCode({{ $form->id }})">
                            <i class="fas fa-code me-2"></i>Get Embed Code
                        </button>
                        <form action="{{ route('admin.forms.duplicate', $form) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-copy me-2"></i>Duplicate Form
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <form id="formEditor" method="POST" action="{{ route('admin.forms.update', $form) }}">
                @csrf
                @method('PUT')

                <!-- Basic Settings Section -->
                <div class="card shadow mb-4 form-section" id="basic-settings">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Basic Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Form Name</label>
                                    <input type="text" class="form-control" name="name" value="{{ $form->name }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="is_active" class="form-label">Status</label>
                                    <select name="is_active" class="form-select">
                                        <option value="1" {{ $form->is_active ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ !$form->is_active ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="header_text" class="form-label">Header Text</label>
                            <input type="text" class="form-control" name="header_text" value="{{ $form->header_text }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="sub_header_text" class="form-label">Sub Header Text</label>
                            <input type="text" class="form-control" name="sub_header_text" value="{{ $form->sub_header_text }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="thank_you_message" class="form-label">Thank You Message</label>
                            <textarea class="form-control" name="thank_you_message" rows="3" required>{{ $form->thank_you_message }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Fields Section -->
                <div class="card shadow mb-4 form-section d-none" id="form-fields">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Form Fields Configuration</h6>
                    </div>
                    <div class="card-body">
                        <div id="fields-container">
                            @foreach($form->fields_config as $fieldName => $config)
                            <div class="card mb-3 field-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <strong>{{ ucfirst(str_replace('_', ' ', $fieldName)) }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" 
                                                   name="fields_config[{{ $fieldName }}][label]" 
                                                   value="{{ $config['label'] }}" 
                                                   placeholder="Field Label">
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="fields_config[{{ $fieldName }}][required]" 
                                                       value="1" {{ $config['required'] ? 'checked' : '' }}>
                                                <label class="form-check-label">Required</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="fields_config[{{ $fieldName }}][show]" 
                                                       value="1" {{ $config['show'] ? 'checked' : '' }}>
                                                <label class="form-check-label">Show</label>
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <i class="fas fa-grip-vertical text-muted drag-handle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Products Section -->
                <div class="card shadow mb-4 form-section d-none" id="products">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Products Management</h6>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addProduct()">
                            <i class="fas fa-plus me-1"></i>Add Product
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="products-container">
                            @foreach($form->products as $index => $product)
                            <div class="card mb-3 product-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Product Name</label>
                                            <input type="text" class="form-control" 
                                                   name="products[{{ $index }}][name]" 
                                                   value="{{ $product['name'] }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Description</label>
                                            <input type="text" class="form-control" 
                                                   name="products[{{ $index }}][description]" 
                                                   value="{{ $product['description'] }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Price (₦)</label>
                                            <input type="number" class="form-control" 
                                                   name="products[{{ $index }}][price]" 
                                                   value="{{ $product['price'] }}" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex flex-column">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="products[{{ $index }}][active]" 
                                                           value="1" {{ $product['active'] ? 'checked' : '' }}>
                                                    <label class="form-check-label">Active</label>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProduct(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Payment Methods Section -->
                <div class="card shadow mb-4 form-section d-none" id="payment-methods">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Payment Methods</h6>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addPaymentMethod()">
                            <i class="fas fa-plus me-1"></i>Add Payment Method
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="payment-methods-container">
                            @foreach($form->payment_methods as $index => $method)
                            <div class="card mb-3 payment-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">Method Name</label>
                                            <input type="text" class="form-control" 
                                                   name="payment_methods[{{ $index }}][name]" 
                                                   value="{{ $method['name'] }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Description</label>
                                            <input type="text" class="form-control" 
                                                   name="payment_methods[{{ $index }}][description]" 
                                                   value="{{ $method['description'] }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Badge (Optional)</label>
                                            <input type="text" class="form-control" 
                                                   name="payment_methods[{{ $index }}][badge]" 
                                                   value="{{ $method['badge'] ?? '' }}" 
                                                   placeholder="e.g., Most Popular">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex flex-column">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="payment_methods[{{ $index }}][active]" 
                                                           value="1" {{ $method['active'] ? 'checked' : '' }}>
                                                    <label class="form-check-label">Active</label>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePaymentMethod(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Delivery Options Section -->
                <div class="card shadow mb-4 form-section d-none" id="delivery-options">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Delivery Options</h6>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addDeliveryOption()">
                            <i class="fas fa-plus me-1"></i>Add Delivery Option
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="delivery-options-container">
                            @foreach($form->delivery_options as $index => $option)
                            <div class="card mb-3 delivery-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">Delivery Type</label>
                                            <input type="text" class="form-control" 
                                                   name="delivery_options[{{ $index }}][name]" 
                                                   value="{{ $option['name'] }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Description</label>
                                            <input type="text" class="form-control" 
                                                   name="delivery_options[{{ $index }}][description]" 
                                                   value="{{ $option['description'] }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Price (₦)</label>
                                            <input type="number" class="form-control" 
                                                   name="delivery_options[{{ $index }}][price]" 
                                                   value="{{ $option['price'] }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex flex-column">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="delivery_options[{{ $index }}][active]" 
                                                           value="1" {{ $option['active'] ? 'checked' : '' }}>
                                                    <label class="form-check-label">Active</label>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDeliveryOption(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Design Section -->
                <div class="card shadow mb-4 form-section d-none" id="design">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Design Customization</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="primary_color" class="form-label">Primary Color (Brand Color)</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" 
                                               name="primary_color" value="{{ $form->primary_color }}">
                                        <input type="text" class="form-control" 
                                               value="{{ $form->primary_color }}" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="background_color" class="form-label">Background Color</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" 
                                               name="background_color" value="{{ $form->background_color }}">
                                        <input type="text" class="form-control" 
                                               value="{{ $form->background_color }}" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="headline_font" class="form-label">Headline Font</label>
                                    <select name="headline_font" class="form-select">
                                        <option value="Playfair Display" {{ $form->headline_font === 'Playfair Display' ? 'selected' : '' }}>Playfair Display</option>
                                        <option value="Georgia" {{ $form->headline_font === 'Georgia' ? 'selected' : '' }}>Georgia</option>
                                        <option value="Times New Roman" {{ $form->headline_font === 'Times New Roman' ? 'selected' : '' }}>Times New Roman</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="font_family" class="form-label">Body Font</label>
                                    <select name="font_family" class="form-select">
                                        <option value="Montserrat" {{ $form->font_family === 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                                        <option value="Open Sans" {{ $form->font_family === 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                                        <option value="Roboto" {{ $form->font_family === 'Roboto' ? 'selected' : '' }}>Roboto</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="show_country_code" 
                                           value="1" {{ $form->show_country_code ? 'checked' : '' }}>
                                    <label class="form-check-label">Show Country Code Selector</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="honeypot_enabled" 
                                           value="1" {{ $form->honeypot_enabled ? 'checked' : '' }}>
                                    <label class="form-check-label">Enable Spam Protection</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Integration Section -->
                <div class="card shadow mb-4 form-section d-none" id="integration">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Integration & Export</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Form URL</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" 
                                               value="{{ route('forms.show', $form) }}" readonly>
                                        <button class="btn btn-outline-secondary" type="button" 
                                                onclick="copyToClipboard('{{ route('forms.show', $form) }}')">
                                            Copy
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Form ID</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="{{ $form->id }}" readonly>
                                        <button class="btn btn-outline-secondary" type="button" 
                                                onclick="copyToClipboard('{{ $form->id }}')">
                                            Copy
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Laravel Route Endpoint</label>
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       value="{{ route('forms.submit', $form) }}" readonly>
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="copyToClipboard('{{ route('forms.submit', $form) }}')">
                                    Copy
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="webhook_url" class="form-label">Webhook URL (Optional)</label>
                            <input type="url" class="form-control" name="webhook_url" 
                                   value="{{ $form->webhook_url }}" 
                                   placeholder="https://your-webhook-endpoint.com">
                            <div class="form-text">Send form submissions to an external webhook</div>
                        </div>

                        <div class="mt-4">
                            <h6>iFrame Embed Code:</h6>
                            <textarea class="form-control" rows="3" readonly onclick="this.select()"><iframe src="{{ route('forms.show', $form) }}" width="100%" height="800" frameborder="0"></iframe></textarea>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.forms.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Forms
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Section Navigation
document.querySelectorAll('[data-section]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Update active link
        document.querySelectorAll('[data-section]').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        // Show target section
        const targetSection = this.getAttribute('data-section');
        document.querySelectorAll('.form-section').forEach(section => {
            section.classList.add('d-none');
        });
        document.getElementById(targetSection).classList.remove('d-none');
    });
});

// Product Management
function addProduct() {
    const container = document.getElementById('products-container');
    const index = container.children.length;
    const productHtml = `
        <div class="card mb-3 product-card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Product Name</label>
                        <input type="text" class="form-control" name="products[${index}][name]" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="products[${index}][description]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Price (₦)</label>
                        <input type="number" class="form-control" name="products[${index}][price]" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex flex-column">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="products[${index}][active]" value="1" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProduct(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', productHtml);
}

function removeProduct(button) {
    button.closest('.product-card').remove();
}

// Payment Method Management
function addPaymentMethod() {
    const container = document.getElementById('payment-methods-container');
    const index = container.children.length;
    const methodHtml = `
        <div class="card mb-3 payment-card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Method Name</label>
                        <input type="text" class="form-control" name="payment_methods[${index}][name]" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="payment_methods[${index}][description]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Badge (Optional)</label>
                        <input type="text" class="form-control" name="payment_methods[${index}][badge]" placeholder="e.g., Most Popular">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex flex-column">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="payment_methods[${index}][active]" value="1" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePaymentMethod(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', methodHtml);
}

function removePaymentMethod(button) {
    button.closest('.payment-card').remove();
}

// Delivery Option Management
function addDeliveryOption() {
    const container = document.getElementById('delivery-options-container');
    const index = container.children.length;
    const optionHtml = `
        <div class="card mb-3 delivery-card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Delivery Type</label>
                        <input type="text" class="form-control" name="delivery_options[${index}][name]" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="delivery_options[${index}][description]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Price (₦)</label>
                        <input type="number" class="form-control" name="delivery_options[${index}][price]" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex flex-column">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="delivery_options[${index}][active]" value="1" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDeliveryOption(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', optionHtml);
}

function removeDeliveryOption(button) {
    button.closest('.delivery-card').remove();
}

// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-secondary');
        
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    });
}

// Embed code function
function copyEmbedCode(formId) {
    fetch(`/admin/forms/${formId}/embed-code`)
        .then(response => response.json())
        .then(data => {
            // Create modal content
            const modalHtml = `
                <div class="modal fade" id="embedCodeModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Embed Code</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">iFrame Code:</label>
                                    <textarea class="form-control" rows="3" readonly>${data.iframe_code}</textarea>
                                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyToClipboard('${data.iframe_code}')">
                                        Copy iFrame Code
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Direct URL:</label>
                                    <input type="text" class="form-control" value="${data.form_url}" readonly>
                                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyToClipboard('${data.form_url}')">
                                        Copy URL
                                    </button>
                                </div>
                                <div>
                                    <label class="form-label">Form ID:</label>
                                    <input type="text" class="form-control" value="${data.form_id}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('embedCodeModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add new modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            new bootstrap.Modal(document.getElementById('embedCodeModal')).show();
        });
}
</script>
@endsection 