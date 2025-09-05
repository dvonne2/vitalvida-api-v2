<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'Laravel') }} - Admin React</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    <!-- React App Styles -->
    @if(file_exists(public_path('admin-react-assets/assets/main-ByW2kolP.css')))
        <!-- Use built assets -->
        <link rel="stylesheet" href="{{ asset('admin-react-assets/assets/main-ByW2kolP.css') }}">
    @else
        <!-- Fallback to Vite dev server -->
        <link rel="stylesheet" href="http://localhost:5175/src/index.css">
    @endif
    
    <!-- Additional styles -->
    <style>
        #admin-react-root {
            width: 100%;
            height: 100vh;
        }
    </style>
</head>
<body>
    <!-- React App Container -->
    <div id="admin-react-root">
        <!-- React will render here -->
    </div>
    
    <!-- React App Scripts -->
    @if(file_exists(public_path('admin-react-assets/assets/main-BIu0WjXA.js')))
        <!-- Use built assets -->
        <script src="{{ asset('admin-react-assets/assets/main-BIu0WjXA.js') }}"></script>
    @else
        <!-- Fallback to Vite dev server -->
        <script type="module" src="http://localhost:5175/src/main.tsx"></script>
    @endif
    
    <!-- Global JavaScript variables for React -->
    <script>
        window.Laravel = {
            csrfToken: '{{ csrf_token() }}',
            apiUrl: '{{ url('/api') }}',
            baseUrl: '{{ url('/') }}',
            user: @json(auth()->user()),
            environment: '{{ app()->environment() }}'
        };
    </script>
</body>
</html> 