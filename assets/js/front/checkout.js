jQuery(function ($) {
 
  if (typeof wc_checkout_params === "undefined") {
    return false;
  }

  $.blockUI.defaults.overlayCSS.cursor = "default";

  var wc_checkout_form = { updateTimer: false,
    dirtyInput: false,
    selectedPaymentMethod: false,
    xhr: false,
    $order_review: $("#order_review"),
    $checkout_form: $("form.checkout"),
    init: function () {
      $(document.body).on("update_checkout", this.update_checkout);
      $(document.body).on("init_checkout", this.init_checkout);

      // Payment methods
      this.$checkout_form.on(
        "click",
        'input[name="payment_method"]',
        this.payment_method_selected
      );

      if ($(document.body).hasClass("woocommerce-order-pay")) {
        this.$order_review.on(
          "click",
          'input[name="payment_method"]',
          this.payment_method_selected
        );
        this.$order_review.on("submit", this.submitOrder);
        this.$order_review.attr("novalidate", "novalidate");
      }

      // Prevent HTML5 validation which can conflict.
      this.$checkout_form.attr("novalidate", "novalidate");

      // Form submission
      this.$checkout_form.on("submit", this.submit);

      // Inline validation
      this.$checkout_form.on(
        "input validate change",
        ".input-text, select, input:checkbox",
        this.validate_field
      );

      // Manual trigger
      this.$checkout_form.on("update", this.trigger_update_checkout);

      // Inputs/selects which update totals
      this.$checkout_form.on(
        "change",
        'select.shipping_method, input[name^="shipping_method"], #ship-to-different-address input, .update_totals_on_change select, .update_totals_on_change input[type="radio"], .update_totals_on_change input[type="checkbox"]',
        this.trigger_update_checkout
      ); // eslint-disable-line max-len
      this.$checkout_form.on(
        "change",
        ".address-field select",
        this.input_changed
      );
      this.$checkout_form.on(
        "change",
        ".address-field input.input-text, .update_totals_on_change input.input-text",
        this.maybe_input_changed
      ); // eslint-disable-line max-len
      this.$checkout_form.on(
        "keydown",
        ".address-field input.input-text, .update_totals_on_change input.input-text",
        this.queue_update_checkout
      ); // eslint-disable-line max-len

      // Address fields
      this.$checkout_form.on(
        "change",
        "#ship-to-different-address input",
        this.ship_to_different_address
      );

      // Trigger events
      this.$checkout_form
        .find("#ship-to-different-address input")
        .trigger("change");
      this.init_payment_methods();

      // Update on page load
      if (wc_checkout_params.is_checkout === "1") {
        $(document.body).trigger("init_checkout");
      }
      if (wc_checkout_params.option_guest_checkout === "yes") {
        $("input#createaccount")
          .on("change", this.toggle_create_account)
          .trigger("change");
      }
    },
    init_payment_methods: function () {
      var $payment_methods = $(".woocommerce-checkout").find(
        'input[name="payment_method"]'
      );

      // If there is one method, we can hide the radio input
      if (1 === $payment_methods.length) {
        $payment_methods.eq(0).hide();
      }

      // If there was a previously selected method, check that one.
      if (wc_checkout_form.selectedPaymentMethod) {
        $("#" + wc_checkout_form.selectedPaymentMethod).prop("checked", true);
      }

      if( 0 === $payment_methods.filter(":checked").length){
        $payment_methods.eq(0).prop("checked", true);
      }
    };
});
