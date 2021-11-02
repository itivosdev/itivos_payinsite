{if $status == 'ok'}
    <p>
      {l s='Your order on %s is complete.' sprintf=[$shop_name] mod='itivos_payinsite'}<br/>
      {l s='Please send us a bank wire with:' mod='itivos_payinsite'}
    </p>
    {include file='module:itivos_payinsite/views/templates/hook/_partials/payment_infos.tpl'}

    <p>
      {l s='Please specify your order reference %s in the bankwire description.' sprintf=[$reference] mod='itivos_payinsite'}<br/>
      {l s='We\'ve also sent you this information by e-mail.' mod='itivos_payinsite'}
    </p>
    <strong>{l s='Your order will be sent as soon as we receive payment.' mod='itivos_payinsite'}</strong>
    <p>
      {l s='If you have questions, comments or concerns, please contact our [1]expert customer support team[/1].' mod='itivos_payinsite' sprintf=['[1]' => "<a href='{$contact_url}'>", '[/1]' => '</a>']}
    </p>
{else}
    <p class="warning">
      {l s='We noticed a problem with your order. If you think this is an error, feel free to contact our [1]expert customer support team[/1].' mod='itivos_payinsite' sprintf=['[1]' => "<a href='{$contact_url}'>", '[/1]' => '</a>']}
    </p>
{/if}
