jQuery(document).ready(function($){
  window.new_stream = ( action, data ) => {
    return $.ajax({
      type: "POST",
      data: JSON.stringify({ action: action, parts: jsObject.parts, data: data }),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce )
      }
    })
      .fail(function(e) {
        $('#error').html(e)
      })
  }

  function build_modal(){
    // add html
    $('#content').empty().html(`
        <input type="hidden" id="stream-grid-id" />
        <div id="panel1" class="first not-new not-send">
            <div class="grid-x">
                <div class="cell">
                    <button type="button" class="button expanded show-new">Register a New Stream</button>
                </div>
                <div class="cell">
                    <button type="button" class="button expanded show-send">Retrieve My Private Link</button>
                </div>
            </div>
        </div>

        <div id="new-panel" class="new not-first not-send" style="display:none;">
            <div class="grid-x">
                <div class="cell panel-note"></div>
                <div class="cell">
                    <label for="name">Name</label>
                    <input type="text" id="name" class="required" placeholder="Name" />
                    <span id="name-error" class="form-error">You're name is required.</span>
                </div>
                <div class="cell">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Email" />
                    <input type="email" id="e2" name="email" class="required" placeholder="Email" />
                    <span id="email-error" class="form-error">You're email is required.</span>
                </div>
                <div class="cell">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" class="required" placeholder="Phone" />
                    <span id="phone-error" class="form-error">You're phone is required.</span>
                </div>
                <div class="cell">
                    <label for="location">City or Address</label>
                    <input type="text" id="location" name="location" placeholder="City or Address" />
                    <span id="phone-error" class="form-error"></span>
                </div>
                <div class="cell center">
                    <button class="button" id="submit-new">Register</button> <span class="loading-spinner"></span><br>
                    <a class="show-first">back</a>
                </div>
            </div>
        </div>

        <div id="send-panel" class="send not-new not-first" style="display:none;">
            <div class="grid-x">
                <div class="cell">
                    <label for="email">Email</label>
                    <input type="email" id="email-send" name="email" placeholder="Email" />
                    <input type="email" id="e2-send" name="email" class="required" placeholder="Email" />
                    <span id="email-error-send" class="form-error">You're email is required.</span>
                </div>
                <div class="cell center">
                    <button class="button" id="submit-send-link">Email me access link</button> <span class="loading-spinner"></span><br>
                    <a class="show-first">back</a>
                </div>
            </div>
        </div>
    `)

    // set listeners
    let send = $('.send')
    let not_send = $('.not-send')
    let new_input = $('.new')
    let not_new = $('.not-new')
    let first = $('.first')
    let not_first = $('.not-first')
    let note = $('.panel-note')
    $('.show-new').on('click', function() {
      new_input.show()
      not_new.hide()
      note.empty()
    })
    $('.show-send').on('click', function() {
      send.show()
      not_send.hide()
      note.empty()
    })
    $('.show-first').on('click', function() {
      first.show()
      not_first.hide()
      note.empty()
    })

    // listen to buttons
    $('#submit-new').on('click', function(){
      create_streamer()
    })
    $('#submit-send-link').on('click', function(){
      retrieve_link_to_streamer()
    })
  } // end function
  build_modal()


  function create_streamer() {
    let spinner = $('.loading-spinner')
    spinner.addClass('active')

    let submit_button = $('#submit-stream')
    submit_button.prop('disabled', true)

    let honey = $('#email').val()
    if ( honey ) {
      submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
      spinner.removeClass('active')
      return;
    }

    let name_input = $('#name')
    let name = name_input.val()
    if ( ! name ) {
      $('#name-error').show()
      submit_button.removeClass('loading')
      name_input.focus(function(){
        $('#name-error').hide()
      })
      submit_button.prop('disabled', false)
      spinner.removeClass('active')
      return;
    }

    let email_input = $('#e2')
    let email = email_input.val()
    if ( ! email ) {
      $('#email-error').show()
      submit_button.removeClass('loading')
      email_input.focus(function(){
        $('#email-error').hide()
      })
      submit_button.prop('disabled', false)
      spinner.removeClass('active')
      return;
    }

    let phone_input = $('#phone')
    let phone = phone_input.val()
    if ( ! phone ) {
      $('#phone-error').show()
      submit_button.removeClass('loading')
      email_input.focus(function(){
        $('#phone-error').hide()
      })
      submit_button.prop('disabled', false)
      spinner.removeClass('active')
      return;
    }
    
    let location_input = $('#location')
    let location = location_input.val()
    // if ( ! location ) {
    //   $('#location-error').show()
    //   submit_button.removeClass('loading')
    //   email_input.focus(function(){
    //     $('#location-error').hide()
    //   })
    //   submit_button.prop('disabled', false)
    //   spinner.removeClass('active')
    //   return;
    // }


    let form_data = {
      name: name,
      email: email,
      phone: phone, 
      location: location
    }
    console.log(form_data)

    window.new_stream( 'new_registration', form_data )
      .done(function(response){
        console.log(response)
        let new_panel = $('#new-panel')
        if ( response.status === 'EMAILED' ) {
          new_panel.empty().html(`
            Excellent! Check your email for a direct link to your stream portal.<br><br>
          `)
        }
        else if ( response.status === 'CREATED' ) {
          new_panel.empty().html(`
            Excellent! You've been sent an email with your stream link. Please, complete your remaining community profile.<br><br>
            <a class="button" href="${response.link}" target="_parent">Open Reporting Portal</a>
          `)
        }
        else if ( response.status === 'FAIL' ) {
          new_panel.empty().html(`
            Oops. Something went wrong. Please, refresh and try again. <a onclick="location.reload();">reload</a>
          `)
        }

        $('.loading-spinner').removeClass('active')
        $('.panel-note').empty()
      })
  }

  function retrieve_link_to_streamer(){
    let spinner = $('.loading-spinner')
    spinner.addClass('active')

    let submit_button = $('#submit-send-link')
    submit_button.prop('disabled', true)

    let honey = $('#email-send').val()
    if ( honey ) {
      submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
      spinner.removeClass('active')
      return;
    }

    let email_input = $('#e2-send')
    let email = email_input.val()
    if ( ! email ) {
      $('#email-error-send').show()
      submit_button.removeClass('loading')
      email_input.focus(function(){
        $('#email-error-send').hide()
      })
      submit_button.prop('disabled', false)
      spinner.removeClass('active')
      return;
    }

    let form_data = {
      email: email
    }

    window.new_stream( 'retrieve_link', form_data )
      .done(function(response){
        console.log(response)
        if ( response ) {
          $('#send-panel').empty().html(`
            Excellent! Go to you email inbox and find your personal link.<br>
          `)
          $('.panel-note').empty()
        } else {
          $('.new').show()
          $('.not-new').hide()
          $('.panel-note').html('Email not found. Please, register.')
        }
        $('.loading-spinner').removeClass('active')

      })
  }
})

