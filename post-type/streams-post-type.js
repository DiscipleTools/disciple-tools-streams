"use strict"
jQuery(document).ready(function($) {

    console.log('stream app')

    let post_id = window.detailsSettings.post_id
    let post_type = window.detailsSettings.post_type
    let post = window.detailsSettings.post_fields
    let field_settings = window.detailsSettings.post_settings.fields

    console.log(field_settings)

    /**
     * Assigned_to
     */
    let assigned_to_input = $(`.js-typeahead-assigned_to`)
    $.typeahead({
        input: '.js-typeahead-assigned_to',
        minLength: 0,
        maxItem: 0,
        accent: true,
        searchOnFocus: true,
        source: TYPEAHEADS.typeaheadUserSource(),
        templateValue: "{{name}}",
        template: function (query, item) {
            return `<div class="assigned-to-row" dir="auto">
        <span>
            <span class="avatar"><img style="vertical-align: text-bottom" src="{{avatar}}"/></span>
            ${_.escape( item.name )}
        </span>
        ${ item.status_color ? `<span class="status-square" style="background-color: ${_.escape(item.status_color)};">&nbsp;</span>` : '' }
        ${ item.update_needed && item.update_needed > 0 ? `<span>
          <img style="height: 12px;" src="${_.escape( window.wpApiShare.template_dir )}/dt-assets/images/broken.svg"/>
          <span style="font-size: 14px">${_.escape(item.update_needed)}</span>
        </span>` : '' }
      </div>`
        },
        dynamic: true,
        hint: true,
        emptyTemplate: _.escape(window.wpApiShare.translations.no_records_found),
        callback: {
            onClick: function(node, a, item){
                API.update_post( post_type, post_id, {assigned_to: 'user-' + item.ID}).then(function (response) {
                    _.set(post, "assigned_to", response.assigned_to)
                    assigned_to_input.val(post.assigned_to.display)
                    assigned_to_input.blur()
                }).catch(err => { console.error(err) })
            },
            onResult: function (node, query, result, resultCount) {
                let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
                $('#assigned_to-result-container').html(text);
            },
            onHideLayout: function () {
                $('.assigned_to-result-container').html("");
            },
            onReady: function () {
                if (_.get(post,  "assigned_to.display")){
                    $('.js-typeahead-assigned_to').val(post.assigned_to.display)
                }
            }
        },
    });
    $('.search_assigned_to').on('click', function () {
        assigned_to_input.val("")
        assigned_to_input.trigger('input.typeahead')
        assigned_to_input.focus()
    })


    /* MICRO APPS SECTION*/
    if ( wpApiShare.post_type_modules.stream_app_module.enabled ) {

        window.stream_app = (type) => {
            /* magicApps defined in DT_Stream_Apps */
            if ( typeof magicApps[type] === 'undefined' ){
                return
            }

            let type_settings = magicApps[type]
            let wrapper = $(`#${type_settings.root}-${type_settings.type}-wrapper`)

            wrapper.empty()

            if ( typeof detailsSettings.post_fields[type_settings.meta_key] !== 'undefined' && detailsSettings.post_fields[type_settings.meta_key] !== '' ){
                wrapper.append(`
                    <a class="button hollow small" href="${wpApiShare.site_url}/${type_settings.root}/${type_settings.type}/${detailsSettings.post_fields[type_settings.meta_key]}">go to link</a>
                    <a class="button hollow small deactivate-magic-form" data-meta_key_name="${type_settings.meta_key}">delete link</a>
                    <a class="button hollow small" data-meta_key_name="${type_settings.meta_key}" data-open="modal-large">show reports</a>
                `)
            }
            else {
                wrapper.append(`
                    <a class="create-magic-form button hollow small" data-meta_key_name="${type_settings.meta_key}" data-meta_key_value="${type_settings.new_key}">activate link</a>
                `)
            }

            $('.create-magic-form').on('click', function(e){
                let meta_key_name = $(this).data('meta_key_name')
                let meta_key_value = $(this).data('meta_key_value')

                let data = {}
                data[meta_key_name] = meta_key_value

                makeRequestOnPosts('POST', detailsSettings.post_type+'/'+detailsSettings.post_id, data)
                    .done((updatedPost)=>{
                        console.log(updatedPost)
                        window.detailsSettings.post_fields = updatedPost
                        window.stream_app(type)
                    })
            })

            $('.deactivate-magic-form').on('click', function(e){
                let meta_key_name = $(this).data('meta_key_name')
                let meta_key_value = $(this).data('meta_key_value')

                let data = {}
                data[meta_key_name] = ''

                makeRequestOnPosts('POST', detailsSettings.post_type+'/'+detailsSettings.post_id, data)
                    .done((updatedPost)=>{
                        window.detailsSettings.post_fields = updatedPost
                        window.stream_app(type)
                    })
            })
        }
        window.stream_app('report')



    } /* end stream app module*/





})
