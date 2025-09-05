<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitalvida AI Command Room</title>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'gray': {
                            900: '#111827',
                            800: '#1f2937',
                            700: '#374151',
                            600: '#4b5563',
                            500: '#6b7280',
                            400: '#9ca3af',
                            300: '#d1d5db',
                            200: '#e5e7eb',
                            100: '#f3f4f6',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
        .ai-command-room {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
    </style>
</head>
<body class="bg-gray-900">
    @yield('content')
    
    <script>
        function commandRoom() {
            return {
                currentTime: new Date().toLocaleTimeString(),
                init() {
                    setInterval(() => {
                        this.currentTime = new Date().toLocaleTimeString();
                    }, 1000);
                }
            }
        }
    </script>
</body>
</html> 