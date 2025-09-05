<?php

namespace App\Services\Marketing\WhatsAppProviders;

interface WhatsAppProviderInterface
{
    public function sendMessage($phoneNumber, $message, $templateName = null, $templateParams = []);
    
    public function checkHealth();
    
    public function getTemplates();
    
    public function getName();
}
