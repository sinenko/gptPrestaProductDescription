<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class GPTDescription extends Module
{
    private $api_key;
    private $model;

    public function __construct()
    {
        $this->name = 'gptdescription';
        $this->tab = 'administration';
        $this->version = '0.1';
        $this->author = 'sinenko';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.4', 'max' => '8.0.4'];
        $this->bootstrap = true;

        $this->api_key = Configuration::get('GPT4INTEGRATION_API_KEY'); // API key
        $this->model = Configuration::get('GPT4INTEGRATION_MODEL'); // GPT model

        parent::__construct();

        $this->displayName = $this->l('GPT-4 product description generator');
        $this->description = $this->l('Automatically generate product descriptions using GPT-4.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    private function generateDescription($productTitle)
    {
        $api_key =  $this->api_key;
        $model = $this->model;

        if (!$api_key || empty($api_key) || !$model || empty($model)) {
            return null;
        }

        $url = 'https://api.openai.com/v1/chat/completions'; // URL GPT-4 API

        $prompt = 'Provide an answer: a description with product specifications for an online store in pure html format (without classes, styles, and id, give the product title id="product-title", make the product summary id="product-summary"), if the product is unknown to you, then write {not-found}: ';
        $prompt .= $productTitle;

        $data = [
            'model' => $model, // Selected GPT model
            'messages' => [[
                  'role' => 'assistant',
                  'content' => $prompt // Text of the request
            ]],
            'max_tokens' => 4000, // Maximum number of tokens in the response
            'n' => 1, // Number of text variants
            'temperature' => 0.1 // Temperature (creative component)
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $response_data = json_decode($response, true);

        if (isset($response_data['choices'][0]['message'])) {
            if ($response_data['choices'][0]['message']['content'] !== '{not-found}') {
                $result['content'] = $response_data['choices'][0]['message']['content'];

            }
           
            $pattern = '/<[^>]*id="product-title"[^>]*>(.*?)<\/[^>]*>/';
            preg_match($pattern, $response_data['choices'][0]['message']['content'], $matches_title);
            
            if (!empty($matches_title)) {
                $result['title'] = $matches_title[1];
            }
            
            
            $pattern = '/<[^>]*id="product-summary"[^>]*>(.*?)<\/[^>]*>/';
            preg_match($pattern, $response_data['choices'][0]['message']['content'], $matches_summary);
            
            if (!empty($matches_summary)) {
                $result['summary'] = $matches_summary[1];
            }
            
            return $result;
        }

        return null;
    }

    public function hookActionProductUpdate($params)
    {
        $product = $params['product'];

        // Checking if the description is already generated
        if($product->description[1] !== '{gpt}' && $product->description[1] !== '<p>{gpt}</p>') { 
            return;
        }
        $productTitle = $product->name[1]; // Getting the product name
        $generatedDescription = $this->generateDescription($productTitle); // Generating the description

        if ($generatedDescription !== null) {
            if(!empty($generatedDescription['title'])) {
        
                $product->name[1] = $generatedDescription['title'];
            }
            if(!empty($generatedDescription['summary'])) {
                $product->description_short[1] = $generatedDescription['summary'];
            }
            if(!empty($generatedDescription['content'])) {
                $product->description[1] = $generatedDescription['content'];
            }
        }
        $product->validateFields();
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionProductUpdate');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {

            $api_key = strval(Tools::getValue('GPT4INTEGRATION_API_KEY'));
            if (!$api_key || empty($api_key)) {
                $output .= $this->displayError($this->l('Invalid API key'));
            } else {
                Configuration::updateValue('GPT4INTEGRATION_API_KEY', $api_key);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }

            if (!Tools::getValue('GPT4INTEGRATION_MODEL')) {
                $output .= $this->displayError($this->l('Invalid model'));
            } else {
                Configuration::updateValue('GPT4INTEGRATION_MODEL', strval(Tools::getValue('GPT4INTEGRATION_MODEL')));
            }

        }

        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Form fields
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('API key'),
                    'name' => 'GPT4INTEGRATION_API_KEY',
                    'size' => 64,
                    'required' => true
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Model'),
                    'name' => 'GPT4INTEGRATION_MODEL',
                    'required' => true,
                    'options' => [
                        'query' => [
                            ['id_option' => 'gpt-4', 'name' => 'gpt-4'],
                            ['id_option' => 'gpt-3.5-turbo', 'name' => 'gpt-3.5-turbo'],
                        ],
                        'id' => 'id_option',
                        'name' => 'name'
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ],
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;      
        $helper->toolbar_scroll = true;    
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        // Load current value
        $helper->fields_value['GPT4INTEGRATION_API_KEY'] = Configuration::get('GPT4INTEGRATION_API_KEY');

        return $helper->generateForm($fields_form);
    }


}