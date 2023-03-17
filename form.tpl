<form action="{$ipn_url|escape}" method="post">
    <input type="hidden" name="payee_id" value="{$payee_id|escape}" />
    <input type="hidden" name="shop_order_number" value="{$shop_order_number|escape}" />
    <input type="hidden" name="bill_amount" value="{$bill_amount|escape}" />
    <input type="hidden" name="bill_currency" value="{$bill_currency|escape}" />
    <input type="hidden" name="description" value="{$description|escape}" />
    <input type="hidden" name="success_url" value="{$success_url|escape}" />
    <input type="hidden" name="failure_url" value="{$failure_url|escape}" />
    <input type="hidden" name="lang" value="{$order_lang_link|escape}" />
    <input type="submit" class="button" value="{$lang->form_to_pay|escape}" />
</form>