<section>
  <p>
    {l s='At the end of your order, you will receive the information by email. Come to our store with your order number to process your payment and continue with the shipment.' mod='itivos_payinsite'}

    {if $itivosPayInSiteReservation_days}
      {l s='Goods will be reserved %s days for you and we\'ll process the order immediately after receiving the payment.' sprintf=[$itivosPayInSiteReservation_days] mod='itivos_payinsite'}
    {/if}
  </p>
</section>

