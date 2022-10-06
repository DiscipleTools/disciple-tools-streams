jQuery(document).ready(function() {

  project_group_tree()
  function project_group_tree() {
    "use strict";
    let chart = jQuery('#chart')
    let spinner = ' <span class="loading-spinner active"></span> '

    chart.empty().html(spinner)
    jQuery('#metrics-sidemenu').foundation('down', jQuery('#streams-menu'));

    let translations = dtMetricsProject.data.translations

    chart.empty().html(`
          <span class="section-header">${window.lodash.escape(translations.title_group_tree)}</span><hr>
           <div class="grid-x grid-padding-x">
           <div class="cell">
               <span>
                  <button class="button hollow toggle-singles" id="highlight-active" onclick="highlight_active();">${window.lodash.escape(translations.highlight_active)/*Highlight Active*/}</button>
               </span>
              <span>
                  <button class="button hollow toggle-singles" id="highlight-churches" onclick="highlight_churches();">${window.lodash.escape(translations.highlight_churches)/*Highlight Churches*/}</button>
              </span>
          </div>
              <div class="cell">
                  <div class="scrolling-wrapper" id="generation_map"><img src="${dtMetricsProject.theme_uri}/dt-assets/images/ajax-loader.gif" width="20px" /></div>
              </div>
          </div>
           <div id="modal" class="reveal" data-reveal></div>
           <br><br>
       `)

    makeRequest('POST', 'metrics/streams/tree' )
      .then(response => {
        // console.log(response)
        jQuery('#generation_map').empty().html(response)
        jQuery('#generation_map li:last-child').addClass('last');
        new Foundation.Reveal(jQuery('#modal'))
      })
  }
})

function open_modal_details( id ) {
  let modal = jQuery('#modal')
  let spinner = ' <span class="loading-spinner active"></span> '
  let translations = dtMetricsProject.data.translations

  modal.empty().html(spinner).foundation('open')

  makeRequest('GET', 'streams/'+window.lodash.escape( id ), null, 'dt-posts/v2/' )
    .then(data => {
      // console.log(data)
      if( data ) {

        modal.empty().append(`
          <div class="grid-x">
              <div class="cell"><span class="section-header">${window.lodash.escape( data.title )}</span><hr style="max-width:100%;"></div>
              <div class="cell">
<!--                  <dl>-->
<!--                      <li>Baptisms</li>-->
<!--                      <li>Disciples</li>-->
<!--                      <li>Churches</li>-->
<!--                  </dl>-->
              </div>
              <div class="cell center"><hr><a href="${window.lodash.escape(window.wpApiShare.site_url)}/streams/${window.lodash.escape( id )}">${translations.view_group /*View Group*/}</a></div>
          </div>
          <button class="close-button" data-close aria-label="Close modal" type="button">
              <span aria-hidden="true">&times;</span>
          </button>
        `)
      }
    })

}



function highlight_active() {
  let list = jQuery('.inactive')
  let button = jQuery('#highlight-active')
  if( button.hasClass('hollow') ) {
    list.addClass('inactive-gray')
    button.removeClass('hollow')
  } else {
    button.addClass('hollow')
    list.removeClass('inactive-gray')
  }
}

function highlight_churches() {
  let list = jQuery('#generation_map span:not(.church)')
  let button = jQuery('#highlight-churches')
  if( button.hasClass('hollow') ) {
    list.addClass('not-church-gray')
    button.removeClass('hollow')
  } else {
    button.addClass('hollow')
    list.removeClass('not-church-gray')
  }
}
