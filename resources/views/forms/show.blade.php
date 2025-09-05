<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $form->header_text }} - {{ $form->name }}</title>
    <link href="https://fonts.googleapis.com/css2?family={{ urlencode($form->headline_font) }}:wght@400;600;700&family={{ urlencode($form->font_family) }}:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: {{ $form->primary_color }};
            --background-color: {{ $form->background_color }};
            --headline-font: '{{ $form->headline_font }}', serif;
            --body-font: '{{ $form->font_family }}', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--body-font);
            background: linear-gradient(135deg, var(--background-color) 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, color-mix(in srgb, var(--primary-color) 80%, black) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .form-header h1 {
            font-family: var(--headline-font);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-header p {
            font-weight: 600;
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .form-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }

        .required {
            color: var(--primary-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            font-family: var(--body-font);
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 10%, transparent);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .phone-input-group {
            display: flex;
            gap: 10px;
        }

        .country-code {
            flex: 0 0 120px;
        }

        .phone-number {
            flex: 1;
        }

        .selector-group {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .selector-option {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .selector-option:last-child {
            border-bottom: none;
        }

        .selector-option:hover {
            background: #f8f9fa;
        }

        .selector-option input[type="radio"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            accent-color: var(--primary-color);
        }

        .option-details {
            flex: 1;
        }

        .option-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .option-description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }

        .option-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .option-badge {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, color-mix(in srgb, var(--primary-color) 80%, black) 100%);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            font-family: var(--body-font);
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px color-mix(in srgb, var(--primary-color) 30%, transparent);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .honeypot {
            position: absolute;
            left: -9999px;
            opacity: 0;
        }

        @media (max-width: 768px) {
            .form-container {
                margin: 10px;
                border-radius: 15px;
            }

            .form-header {
                padding: 30px 20px;
            }

            .form-header h1 {
                font-size: 1.8rem;
            }

            .form-body {
                padding: 30px 20px;
            }

            .phone-input-group {
                flex-direction: column;
            }

            .country-code {
                flex: none;
            }

            .selector-option {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1>{{ $form->header_text }}</h1>
            <p>{{ $form->sub_header_text }}</p>
        </div>

        <div class="form-body">
            @if(session('success'))
            <div class="success-message" style="display: block;">
                {{ session('success') }}
            </div>
            @else
            <form id="orderForm" method="POST" action="{{ route('forms.submit', $form) }}">
                @csrf
                
                @if($form->honeypot_enabled)
                <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off">
                @endif

                {{-- Dynamic Fields Based on Configuration --}}
                @if($form->fields_config['name']['show'])
                <div class="form-group">
                    <label for="name">{{ $form->fields_config['name']['label'] }} 
                        @if($form->fields_config['name']['required'])<span class="required">*</span>@endif
                    </label>
                    <input type="text" id="name" name="name" 
                           @if($form->fields_config['name']['required']) required @endif>
                </div>
                @endif

                @if($form->fields_config['phone']['show'])
                <div class="form-group">
                    <label for="phone">{{ $form->fields_config['phone']['label'] }} 
                        @if($form->fields_config['phone']['required'])<span class="required">*</span>@endif
                    </label>
                    @if($form->show_country_code)
                    <div class="phone-input-group">
                        <select name="country_code" class="country-code" required>
                            <option value="+234" selected>+234 (Nigeria)</option>
                            <option value="+1">+1 (USA/Canada)</option>
                            <option value="+44">+44 (UK)</option>
                            <option value="+233">+233 (Ghana)</option>
                            <option value="+254">+254 (Kenya)</option>
                        </select>
                        <input type="tel" id="phone" name="phone" class="phone-number" 
                               placeholder="8012345678" @if($form->fields_config['phone']['required']) required @endif>
                    </div>
                    @else
                    <input type="tel" id="phone" name="phone" 
                           @if($form->fields_config['phone']['required']) required @endif>
                    @endif
                </div>
                @endif

                @if($form->fields_config['email']['show'])
                <div class="form-group">
                    <label for="email">{{ $form->fields_config['email']['label'] }}
                        @if($form->fields_config['email']['required'])<span class="required">*</span>@endif
                    </label>
                    <input type="email" id="email" name="email" 
                           @if($form->fields_config['email']['required']) required @endif>
                </div>
                @endif

                @if($form->fields_config['state']['show'])
                <div class="form-group">
                    <label for="state">{{ $form->fields_config['state']['label'] }} 
                        @if($form->fields_config['state']['required'])<span class="required">*</span>@endif
                    </label>
                    <select id="state" name="state" @if($form->fields_config['state']['required']) required @endif>
                        <option value="">Select State</option>
                        <option value="Lagos">Lagos</option>
                        <option value="Abuja">Abuja</option>
                        <option value="Rivers">Rivers</option>
                        <option value="Abia">Abia</option>
                        <option value="Adamawa">Adamawa</option>
                        <option value="Akwa Ibom">Akwa Ibom</option>
                        <option value="Anambra">Anambra</option>
                        <option value="Bauchi">Bauchi</option>
                        <option value="Bayelsa">Bayelsa</option>
                        <option value="Benue">Benue</option>
                        <option value="Borno">Borno</option>
                        <option value="Cross River">Cross River</option>
                        <option value="Delta">Delta</option>
                        <option value="Ebonyi">Ebonyi</option>
                        <option value="Edo">Edo</option>
                        <option value="Ekiti">Ekiti</option>
                        <option value="Enugu">Enugu</option>
                        <option value="Gombe">Gombe</option>
                        <option value="Imo">Imo</option>
                        <option value="Jigawa">Jigawa</option>
                        <option value="Kaduna">Kaduna</option>
                        <option value="Kano">Kano</option>
                        <option value="Katsina">Katsina</option>
                        <option value="Kebbi">Kebbi</option>
                        <option value="Kogi">Kogi</option>
                        <option value="Kwara">Kwara</option>
                        <option value="Nasarawa">Nasarawa</option>
                        <option value="Niger">Niger</option>
                        <option value="Ogun">Ogun</option>
                        <option value="Ondo">Ondo</option>
                        <option value="Osun">Osun</option>
                        <option value="Oyo">Oyo</option>
                        <option value="Plateau">Plateau</option>
                        <option value="Sokoto">Sokoto</option>
                        <option value="Taraba">Taraba</option>
                        <option value="Yobe">Yobe</option>
                        <option value="Zamfara">Zamfara</option>
                    </select>
                </div>
                @endif

                @if($form->fields_config['address']['show'])
                <div class="form-group">
                    <label for="address">{{ $form->fields_config['address']['label'] }} 
                        @if($form->fields_config['address']['required'])<span class="required">*</span>@endif
                    </label>
                    <textarea id="address" name="address" placeholder="Enter your complete delivery address" 
                              @if($form->fields_config['address']['required']) required @endif></textarea>
                </div>
                @endif

                {{-- Products Selection --}}
                <div class="form-group">
                    <label>Select Your Package <span class="required">*</span></label>
                    <div class="selector-group">
                        @foreach($form->products as $product)
                        @if($product['active'])
                        <div class="selector-option">
                            <input type="radio" id="product_{{ $loop->index }}" name="product" value="{{ $product['name'] }}" required>
                            <div class="option-details">
                                <div class="option-name">{{ $product['name'] }}</div>
                                <div class="option-description">{{ $product['description'] }}</div>
                                <div class="option-price">₦{{ number_format($product['price']) }}</div>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>

                @if($form->fields_config['promo_code']['show'])
                <div class="form-group">
                    <label for="promo_code">{{ $form->fields_config['promo_code']['label'] }}</label>
                    <input type="text" id="promo_code" name="promo_code" placeholder="Enter promo code if you have one">
                </div>
                @endif

                {{-- Payment Methods --}}
                <div class="form-group">
                    <label>Payment Method <span class="required">*</span></label>
                    <div class="selector-group">
                        @foreach($form->payment_methods as $method)
                        @if($method['active'])
                        <div class="selector-option">
                            <input type="radio" id="payment_{{ $loop->index }}" name="payment_method" value="{{ $method['name'] }}" required>
                            <div class="option-details">
                                <div class="option-name">{{ $method['name'] }}</div>
                                <div class="option-description">{{ $method['description'] }}</div>
                                @if(!empty($method['badge']))
                                <div class="option-badge">{{ $method['badge'] }}</div>
                                @endif
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>

                {{-- Delivery Options --}}
                <div class="form-group">
                    <label>Preferred Delivery Type <span class="required">*</span></label>
                    <div class="selector-group">
                        @foreach($form->delivery_options as $option)
                        @if($option['active'])
                        <div class="selector-option">
                            <input type="radio" id="delivery_{{ $loop->index }}" name="delivery_preference" value="{{ $option['name'] }}" required>
                            <div class="option-details">
                                <div class="option-name">{{ $option['name'] }}</div>
                                <div class="option-description">{{ $option['description'] }}</div>
                                <div class="option-price">₦{{ number_format($option['price']) }}</div>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>

                @if($form->fields_config['source']['show'])
                <div class="form-group">
                    <label for="source">{{ $form->fields_config['source']['label'] }} 
                        @if($form->fields_config['source']['required'])<span class="required">*</span>@endif
                    </label>
                    <select id="source" name="source" @if($form->fields_config['source']['required']) required @endif>
                        <option value="">Select Option</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Instagram">Instagram</option>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Friend">Friend/Referral</option>
                        <option value="Google">Google Search</option>
                        <option value="YouTube">YouTube</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                @endif

                <button type="submit" class="submit-btn">
                    CLICK HERE TO ORDER NOW
                </button>
            </form>
            @endif
        </div>
    </div>

    <script>
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            // Check for same-day delivery time restriction
            const deliveryInputs = document.querySelectorAll('input[name="delivery_preference"]');
            const checkedDelivery = document.querySelector('input[name="delivery_preference"]:checked');
            
            if (checkedDelivery && checkedDelivery.value === 'Same-Day Delivery') {
                const now = new Date();
                const currentHour = now.getHours();
                
                if (currentHour >= 12) {
                    e.preventDefault();
                    alert('Same-day delivery is only available for orders placed before 12:00 PM. Please select Express or Standard delivery.');
                    return false;
                }
            }

            // Show loading state
            const submitBtn = document.querySelector('.submit-btn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'PROCESSING YOUR ORDER...';
            submitBtn.disabled = true;

            // Combine country code and phone number for backend
            @if($form->show_country_code)
            const countryCode = document.querySelector('select[name="country_code"]').value;
            const phoneNumber = document.querySelector('input[name="phone"]').value;
            
            // Create hidden input with combined phone number
            const hiddenPhone = document.createElement('input');
            hiddenPhone.type = 'hidden';
            hiddenPhone.name = 'full_phone';
            hiddenPhone.value = countryCode + phoneNumber;
            this.appendChild(hiddenPhone);
            @endif
        });
    </script>
</body>
</html> 