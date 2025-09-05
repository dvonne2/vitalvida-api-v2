@extends('layouts.admin')

@section('title', 'Create New Form')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Create New Form</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.forms.store') }}" method="POST">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Form Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="header_text" class="form-label">Form Header Text <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('header_text') is-invalid @enderror" 
                                   id="header_text" name="header_text" 
                                   value="{{ old('header_text', 'Place Your Order') }}" required>
                            @error('header_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="sub_header_text" class="form-label">Sub Header Text <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('sub_header_text') is-invalid @enderror" 
                                   id="sub_header_text" name="sub_header_text" 
                                   value="{{ old('sub_header_text', 'Only Serious Buyers Should Fill This Form') }}" required>
                            @error('sub_header_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.forms.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Form</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 