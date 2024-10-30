(function($)
{
  var opt = CF7_PAYMENTS_I18N, intent_secret, stripe, card

  $(document).ready(function()
  {
    var cont = $('#cf7-payments')

    if ( ! cont.length )
      return

    if ( cont.data('stripe_key') ) {
      // Create a Stripe client
      stripe = Stripe(cont.data('stripe_key'))

      // Create an instance of Elements
      var elements = stripe.elements()

      // Custom styling can be passed to options when creating an Element.
      // (Note that this demo uses a wider set of styles than the guide below.)
      var style = {
        base: {
          lineHeight: '28px',
          fontFamily: '"Open Sans", "Helvetica Neue", Helvetica, sans-serif',
          fontSmoothing: 'antialiased',
          fontSize: '16px',
          '::placeholder': {
          }
        },
      }

      // Create an instance of the card Element
      card = elements.create('card', {style: style})

      // Add an instance of the card Element into the `card-element` <div>
      card.mount('#card-element')

      // Handle real-time validation errors from the card Element.
      card.addEventListener('change', function(event) {
        var displayError = document.getElementById('card-errors')
        if (event.error) {
          displayError.textContent = event.error.message
        } else {
          displayError.textContent = ''
        }
      })
    }

    var form_id = +$('#cf7-payments').closest('form').find('[name=_wpcf7]').val()

    if ( form_id > 0 && opt.browser_payments[form_id] ) {
      processPaymentId(opt.browser_payments[form_id])
    } else {
      $('#cf7-payments').removeClass('loading')
    }

    // cookie listener
    setInterval(function()
    {
      var cont = $('#cf7-payments')

      if ( ! cont.hasClass('success') )
        return

      var form_id = +cont.closest('form').find('[name=_wpcf7]').val()

      if ( ! new RegExp('cf7-payment_' + form_id + '=.+').test(document.cookie) ) {
        intent_secret = null

        if ( ! $('[name=cf7_paymens_method]:checked', cont).length ) {
          $('#cf7-payments-stripe, #cf7-payments-paypal', cont).hide()
        }

        return cont.removeClass('success')
      }
    }, 1000)

    testCookies() || $('.cf7-payment-cookies-notice').show()
  })

  $(document).on('change', '#cf7-payments [name=cf7_paymens_method]', function(e)
  {
    e.preventDefault()
    $('#cf7-payments-paypal, #cf7-payments-stripe').hide()
    $('#cf7-payments-' + this.value).show()
  })

  $(document).on('click', '#cf7-payments .stripe-submit', function(event){
    event.preventDefault()

    var button = $(this)
      , form = button.closest('form')
      , form_id = +$('[name=_wpcf7]', form).val()
        
    var confirmSetup = function()
    {
      if ( ! intent_secret ) {
        $('#cf7-payments .stripe-submit').prop('disabled', false)
        $('#cf7-payments #stripe-submit-cont img').hide()
        return
      }

      var email = form.find('.wpcf7-form-control[type=email]').val()

      stripe.confirmCardPayment(intent_secret, {
        payment_method: {
          card: card,
          billing_details: email ? { email: email } : {},
        }
      }).then(function(result) {
        if ( ! result.error ) {
          $('#cf7-payments .stripe-submit').prop('disabled', 'disabled')
          $('#cf7-payments #stripe-submit-cont img').show()

          // The payment has been processed!
          if ( result.paymentIntent.status === 'succeeded' ) {
            $.ajax({
              type: 'POST',
              url: opt.rest_url.concat(form_id, '/payment-intent/validate', '?_wpnonce=', opt.rest_nonce),
              data: {
                intent_id: result.paymentIntent.id
              },
              success: function(id)
              {
                $('#cf7-payments .stripe-submit').prop('disabled', false)
                $('#cf7-payments #stripe-submit-cont img').hide()
                processPaymentId(result.paymentIntent.id)
              },
              error: function()
              {
                $('#cf7-payments .stripe-submit').prop('disabled', false)
                $('#cf7-payments #stripe-submit-cont img').hide()
              }
            })
          }
        } else {
          $('#cf7-payments .stripe-submit').prop('disabled', false)
          $('#cf7-payments #stripe-submit-cont img').hide()
          document.getElementById('card-errors').textContent = result.error.message
        }
      })
    }

    $('#cf7-payments .stripe-submit').prop('disabled', 'disabled')
    $('#cf7-payments #stripe-submit-cont img').show()

    if ( ! intent_secret ) {
      getIntentSecret(form_id, function(secret)
      {
        intent_secret = secret
        return confirmSetup()
      })
    } else {
      confirmSetup()
    }
  })

  $(document).on('click', '#cf7-payments #cf7-payments-paypal a', function(e)
  {
    e.preventDefault()

    if ( $('#cf7-payments #cf7-payments-paypal img').is(':visible') )
      return

    var form_id = +$(this).closest('form').find('[name=_wpcf7]').val()
      , _popup = popup(opt.rest_url.concat(form_id, '/paypal/checkout', '?_wpnonce=', opt.rest_nonce), 520, 570)
      , timer

    $('#cf7-payments #cf7-payments-paypal img').show()

    timer = setInterval(function()
    { 
      if ( _popup.closed ) {
        clearInterval(timer)
        var payment_id = getCookie('cf7-payment_' + form_id)
        payment_id && processPaymentId(payment_id)
        $('#cf7-payments #cf7-payments-paypal img').hide()
      }
    }, 500)
  })

  function getIntentSecret(form_id, then, onerror)
  {
    return $.ajax({
      type: 'GET',
      url: opt.rest_url.concat(form_id, '/payment-intent', '?_wpnonce=', opt.rest_nonce),
      success: function(secret)
      {
        $('#cf7-payments .stripe-submit').prop('disabled', false)
        ;'function' == typeof then && then(secret)
      },
      error: function()
      {
        $('#cf7-payments .stripe-submit').prop('disabled', false)
        ;'function' == typeof onerror && onerror()
      }
    })
  }

  function processPaymentId(id)
  {
    var cont = $('#cf7-payments')
      , form = cont.closest('form')
      , form_id = +form.find('[name=_wpcf7]').val()

    cont.addClass('loading')

    $.ajax({
      type: 'GET',
      url: opt.rest_url.concat(form_id, '/payment-id/', id, '?_wpnonce=', opt.rest_nonce),
      success: function(res)
      {
        cont.removeClass('loading')
        cont.addClass('success')
      },
      error: function()
      {
        cont.removeClass('loading')
        cont.removeClass('success')
      }
    })
  }

  function popup(url, w, h)
  {
    var dualScreenLeft = window.screenLeft !==  undefined ? window.screenLeft : window.screenX
    var dualScreenTop = window.screenTop !==  undefined   ? window.screenTop  : window.screenY

    var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width
    var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height

    var systemZoom = width / window.screen.availWidth
    var left = (width - w) / 2 / systemZoom + dualScreenLeft
    var top = (height - h) / 2 / systemZoom + dualScreenTop
    var newWindow = window.open(url, null, 'scrollbars=yes,width=' + Math.min(520, w / systemZoom) + ',height=' + Math.min(570, h / systemZoom) + ',top=' + top + ',left=' + left)

    if (window.focus) newWindow.focus()

    return newWindow
  }

  function getCookie(name) {
    var value = '; '.concat(document.cookie)
    var parts = value.split(' '.concat(name, '='))
    if (parts.length === 2) return parts.pop().split(';').shift()
  }

  function testCookies()
  {
    try {
      document.cookie = '50cd3=1'
      var on = document.cookie.indexOf('50cd3=') !== -1
      document.cookie = '50cd3=1; expires=Thu, 01-Jan-1970 00:00:01 GMT'
      return on
    } catch (e) {
      return false
    }
  }
})( window.jQuery )