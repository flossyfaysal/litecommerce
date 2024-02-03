jQuery(function ($) {
 
  if (typeof wc_checkout_params === "undefined") {
    return false;
  }

  $.blockUI.defaults.overlayCSS.cursor = "default";

  var lc_chckout_form = { 
    updateTimer: false,
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

      if ($(document.body).hasClass("litecommerce-order-pay")) {
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
      var $payment_methods = $(".litecommerce-checkout").find(
        'input[name="payment_method"]'
      );

      // If there is one method, we can hide the radio input
      if (1 === $payment_methods.length) {
        $payment_methods.eq(0).hide();
      }

      // If there was a previously selected method, check that one.
      if (lc_chckout_form.selectedPaymentMethod) {
        $("#" + lc_chckout_form.selectedPaymentMethod).prop("checked", true);
      }

      if( 0 === $payment_methods.filter(":checked").length){
        $payment_methods.eq(0).prop("checked", true);
      }

      var checkedPaymentMethod = $payment_methods.filter(":checked").eq(0).prop("id");

      if($payment_methods.length > 1){
        $('div.payment_box:not(".' + checkedPaymentMethod + '")').filter(":visible").slideUp(0);
      }

      $payment_methods.filter(":checked").eq(0).trigger("click");

      get_payment_method: function(){
        return lc_chckout_form.$checkout_form.find('input[name="payment_method"]:checked').val();
      }

      payment_method_selected: function(e){
        e.stopPropogation();
        if($(".payment_methods input.input-radio").length > 1){
            var target_payment_box = $("div.payment_box." + $(this).attr("ID")),
            is_checked = $(this).is(":checked");

            if(is_checked && !target_payment_box.is(":visible")){
                $("div.payment_box").filter(":visible").slideUp(230);
                if(is_checked){
                    target_payment_box.slideDown(230);
                }
            }
        }else{
            $("div.payment_box").show();
        }

        if($(this).data("order_button_text")){
            $("#place_order").text($(this).data("order_button_text"));
        }else{
            $("#place_order").text($("#place_order").data("value"));
        }

        var selectedPaymentMethod = $('.litecommerce-checkout input[name="payment_method"]:checked').attr("id");

        if(selectedPaymentMethod !== lc_checkout_form.selectedPaymentMethod){
            $(document.body).trigger("payment_method_selected");
        }

        lc_checkout_form.selectedPaymentMethod = selectedPaymentMethod;
      },
      toggle_create_account: function(){
        $("div.create-account").hide();
        if($(this).is(":checked")){
          $("#account_password").val("").trigger("change");
          $("div.create-account").slideDown();
        }
      },
      init_checkout: function(){
        $(document.body).trigger("update_checkout");
      },
      maybe_input_changed: function(e){
        if(lc_chckout_form.dirtyInput){
          lc_checkout_form.input_changed(e);
        }
      },
      queue_update_checkout: function(e){
        var code = e.keyCode || e.which || 0;
        if(code === 9){
          return true; 
        }

        lc_checkout_form.dirtyInput = this;
        lc_checkout_form.reset_update_checkout_timer();
        lc_checkout_form.updateTimer = setTimeout(
          lc_checkout_form.maybe_update_checkout,"1000"
        );
      },
      trigger_update_checkout: function(){
          lc_checkout_form.reset_update_checkout_timer();
          lc_checkout_form.dirtyInput = false; 
          $(document.body).trigger("update_checkout");
      },
      maybe_update_checkout: function(){
        var update_totals = true;
        if($(lc_checkout_form.dirtyInput).length){
          var $required_inputs = $(lc_checkout_form.dirtyInput).closets("div").find(".address-field.validate-required");
          
          if($required_inputs.length){
            $required_inputs.each(function(){
              if($(this).find("input.input-text").val() === ""){
                update_totals = false;
              }
            });
          }
        }
        if(update_totals){
          lc_checkout_form.trigger_update_checkout();
        }
      },

      ship_to_different_address: function(){
        $("div.shipping_address").hide();
        if($(this).is(":checked")){
          $("div.shipping_address").slideDown();
        }
      },
      reset_update_checkout_timer: function(){
        clearTimeout(lc_chckout_form.updateTimer());
      },
      is_valid_json: function(raw_json){
        try{
          var json = JSON.parse(raw_json);
          return json && "object" === typeof json;
        }catch(e){
          return false;
        }
      },
      validate_field: function(e){
        var $this = $(this);
        $parent = $this.closets(".form-row");
        validated = true,
        validate_required = $parent.is(".validate-required"),
        validate_email  = $parent.is(".validate-email"),
        validate_phone = $parent.is(".validate-phone"),
        pattern = "",
        event_type = e.type;

        if("input" === event_type){
          $parent.removeClass(
            "litecommerce-invalid",
            "litecommerce-invalid-required-field",
            "litecommerce-invalid-email",
            "litecommerce-invalid-phone",
            "litecommerce-validated"
          );

          if(validate_required){
            if("checkbox" === $this.attr("type") && !$this.is(":checked")){
              $parent
              .removeClass("litecommerce-validated")
              .addClass("litecommerce-invalid litecommerce-invalid-required-field");
              validated = false;
            }
          }else if( $this.val() === ""){
            $parent.removeClass("woocommercc-validated")
            .addClass("litecommerce-invalide litecommerce-invalid-required-field");
            validated = false;
          }

          if(validated){
            $parent
            .removeClass(
              "litecommerce-invalid litecommerce-invalid-required-field litecommerce-invalid-email litecommerce-invalid-phone"
            ).addClass("litecommerce-validated");
          }
        }
      },

      update_checkout: function(event, args){}
      update_checkout_action: function(args){}
    };

});
