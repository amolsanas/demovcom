<?php

/**
   * @file
   * Contains \Drupal\resume\Form\ResumeForm.
    */

namespace Drupal\Compliancegpsmodule\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class CurrencyConverter extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'currency_converter_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $currenciesTree = \Drupal::entityManager()->getStorage('taxonomy_term')->loadTree('currencies');
        $currencies = array("" => "--Select--");
        foreach ($currenciesTree as $index => $termData) {
            $currencies[$termData->name] = $termData->name;
        }


        $default_to_country = "";
        $default_to_country_currency = "";
        $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
        $user_country = $user->get('field_country')->getValue();
        if (isset($user_country[0]['target_id']) && $user_country[0]['target_id'] != "" && $user_country[0]['target_id'] != 0) {
            $default_to_country = $user_country[0]['target_id'];
            $tempTermData = \Drupal\taxonomy\Entity\Term::load($default_to_country)->get('field_select_currency')->getValue();
            $tempTermData_currency = \Drupal\taxonomy\Entity\Term::load($tempTermData[0]['target_id'])->get('name')->getValue();
            $default_to_country_currency = isset($tempTermData_currency[0]['value']) ? $tempTermData_currency[0]['value'] : "";
        }

        $form['from_currency'] = array(
            '#type' => 'select',
//            '#title' => t('From'),
//            '#options' => array("" => "--select--", "AED" => "AED", "AFN" => "AFN", "ALL" => "ALL", "USD" => "USD"),
            '#options' => $currencies,
            '#required' => TRUE,
            '#default_value' => isset($_GET['from_currency']) ? $_GET['from_currency'] : 'USD',
            '#weight' => 1,
        );
        $form['value_to_convert'] = array(
            '#type' => 'textfield',
//            '#title' => t('Enter value'),
            '#default_value' => isset($_GET['value_to_convert']) ? $_GET['value_to_convert'] : '',
            '#required' => TRUE,
            '#attributes' => array('class' => array('custom-class-value-to-convert')),
            '#weight' => 2,
        );
        $form['to_currency'] = array(
            '#type' => 'select',
//            '#title' => t('To'),
//            '#options' => array("" => "--select--", "AED" => "AED", "AFN" => "AFN", "ALL" => "ALL", "USD" => "USD"),
            '#options' => $currencies,
            '#default_value' => isset($_GET['to_currency']) ? $_GET['to_currency'] : $default_to_country_currency,
            '#required' => TRUE,
            '#weight' => 4,
        );

        $form['converted_value'] = array(
            '#type' => 'textfield',
//            '#title' => t('Convetred value'),
//            '#required' => TRUE,
            '#default_value' => isset($_GET['converted_value']) ? $_GET['converted_value'] : '',
            '#attributes' => array('class' => array('custom-class-converted-value')),
            '#weight' => 5,
        );
        $form['actions']['#type'] = 'actions';
        $form['actions']['#weight'] = 3;
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Convert'),
            '#button_type' => 'primary',
            '#attributes' => array('class' => array('custom-class-convert')),
        );
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $selected_countries = $form_state->getValue('select_country');
        if (count($selected_countries) == 1 && isset($selected_countries[0])) {
            $form_state->setErrorByName('select_country', $this->t('Please select country'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $ratesTo = $form_state->getValue('to_currency');
        $valueToConvert = $form_state->getValue('value_to_convert');
        $ratesFrom = $form_state->getValue('from_currency');
//        echo "<pre>";
//        print_r($ratesTo);
//        print_r($valueToConvert);
//        print_r($ratesFrom);
//        exit;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://openexchangerates.org/api/latest.json?app_id=ab2e82e73b044537b945be08fb17e107",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "postman-token: ba16eff3-1a14-347e-56e4-51fcf6b9dfcc"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
//            echo $response;
        }
        $decoded_response = json_decode($response);
        $updated_rates = $decoded_response->rates;
        $converted_value = ($updated_rates->$ratesTo * $valueToConvert) / $updated_rates->$ratesFrom;
        $form_state->setRedirect('Compliancegpsmodule.currency_converter', array("converted_value" => $converted_value, "from_currency" => $ratesFrom, "to_currency" => $ratesTo, "value_to_convert" => $valueToConvert));
    }

}
