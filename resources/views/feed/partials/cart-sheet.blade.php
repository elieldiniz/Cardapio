{{--
    The one bottom sheet of the ordering flow (RF-04, RF-06, UI-02): choices of a product,
    the cart, the checkout steps and the confirmation all render into data-order-body.
    Empty until cart.js loads; the markup is only the frame.
--}}
<div class="sheet-backdrop" data-order-backdrop hidden></div>
<section class="sheet order-sheet" data-order-sheet role="dialog" aria-modal="true" aria-label="Seu pedido" hidden>
    <div class="sheet__handle" aria-hidden="true"></div>
    <button type="button" class="order-sheet__close" data-order-close aria-label="Fechar">×</button>
    <div class="order-sheet__body" data-order-body></div>
</section>
