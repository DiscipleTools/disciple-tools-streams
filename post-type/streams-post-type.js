"use strict"
jQuery(document).ready(function($) {

    let post_id = window.detailsSettings.post_id
    let post_type = window.detailsSettings.post_type
    let post = window.detailsSettings.post_fields
    let field_settings = window.detailsSettings.post_settings.fields

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
    if ( typeof streams_report_module !== 'undefined' ) {

        window.stream_report_buttons = () => {
            /* magicApps defined in DT_Stream_Apps */
            if ( typeof streams_report_module.report === 'undefined' ){
                return
            }

            let type_settings = streams_report_module.report
            let wrapper = $(`#${type_settings.root}-${type_settings.type}-wrapper`)

            wrapper.empty()

            if ( typeof detailsSettings.post_fields[type_settings.meta_key] !== 'undefined' && detailsSettings.post_fields[type_settings.meta_key] !== '' ){
                wrapper.append(`
                    <a class="button hollow" target="_blank" href="${wpApiShare.site_url}/${type_settings.root}/${type_settings.type}/${detailsSettings.post_fields[type_settings.meta_key]}">go to link</a>
                    <a class="button hollow deactivate-magic-form" data-meta_key_name="${type_settings.meta_key}">delete link</a>
                `)
            }
            else {
                wrapper.append(`
                    <a class="create-magic-form button hollow" data-meta_key_name="${type_settings.meta_key}">activate link</a>
                `)
            }

            $('.create-magic-form').on('click', function(e){
                let data = {}
                data[type_settings.meta_key] = type_settings.new_key

                makeRequestOnPosts('POST', detailsSettings.post_type+'/'+detailsSettings.post_id, data)
                    .done((updatedPost)=>{
                        console.log(updatedPost)
                        window.detailsSettings.post_fields = updatedPost
                        window.stream_report_buttons()
                    })
            })

            $('.deactivate-magic-form').on('click', function(e){
                let meta_key_name = $(this).data('meta_key_name')

                let data = {}
                data[meta_key_name] = ''

                makeRequestOnPosts('POST', detailsSettings.post_type+'/'+detailsSettings.post_id, data)
                    .done((updatedPost)=>{
                        window.detailsSettings.post_fields = updatedPost
                        window.stream_report_buttons()
                    })
            })


            $(`#${type_settings.root}-${type_settings.type}-manage-reports`).on('click', function(e){
                let spinner = $('.loading-spinner')
                let title = $('#modal-full-title')
                let content = $('#modal-full-content')
                title.empty().html(`<h2>Reports <a class="button hollow small" href="${wpApiShare.site_url}/${type_settings.root}/${type_settings.type}/${detailsSettings.post_fields[type_settings.meta_key]}">edit</a></h2>`)
                content.empty().html(`
                    <span class="loading-spinner active"></span>
                    <div class="grid-x grid-padding-x" ">
                        <div class="cell medium-4" id="reports-list" style="height:${window.innerHeight - 80}px;overflow-y:scroll;"></div>
                        <div class="cell medium-3" id="reports-stats" style="height:${window.innerHeight - 80}px;overflow-y:scroll;"></div>
                        <div class="cell medium-5" id="reports-map"></div>
                    </div>
                `)

                // get report list
                $.ajax({
                    type: "POST",
                    data: JSON.stringify({ post_id: detailsSettings.post_id } ),
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: wpApiShare.root + streams_report_module.report.root + '/v1/' + streams_report_module.report.type + '/all',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiShare.nonce )
                    }
                })
                    .done(function(data){
                        console.log(data)
                        /* list */
                        let list = $('#reports-list')
                        list.empty()
                        $.each(data.reports, function(i,v){
                            list.prepend(`
                                 <div class="cell">
                                     <div class="section-subheader"><span class="title-year">${_.escape( i )}</span> </div>
                                     <table class="hover"><tbody id="report-list-${_.escape( i )}"></tbody></table>
                                 </div>
                             `)
                            let inner_list = $('#report-list-'+_.escape( i ))
                            $.each(v, function(ii,vv){
                                inner_list.append(`
                                <tr><td>${_.escape( vv.value )} total ${_.escape( vv.payload.type )} in ${_.escape( vv.label )}</td><td style="vertical-align: middle;"></td></tr>
                            `)
                            })
                        })

                        $('.delete-report').on('click', function(e){
                            let id = $(this).data('id')
                            $(this).attr('disabled', 'disabled')
                            window.delete_report( id )
                        })

                        /* stats */
                        let stats = $('#reports-stats')
                        stats.empty()
                        $.each(data.stats, function(i,v) {
                            stats.prepend(`
                            <div class="grid-x">
                                 <div class="cell section-subheader">
                                    ${i}
                                </div>
                                <div class="cell">
                                    <span class="stat-heading">Total Groups</span>: 
                                    <span id="total_groups" class="stat-number">${v.total_groups}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Total Baptisms</span>: 
                                    <span id="total_groups" class="stat-number">${v.total_baptisms}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged Countries</span>: 
                                    <span id="total_groups" class="stat-number">${v.total_countries}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged States</span>: 
                                    <span id="total_groups" class="stat-number">${v.total_states}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged Counties</span>: 
                                    <span id="total_groups" class="stat-number">${v.total_counties}</span>
                                </div>
                            </div>
                            <hr>
                        `)
                        })

                        /* geojson */
                        mapboxgl.accessToken = dtMapbox.map_key;
                        var map = new mapboxgl.Map({
                            container: 'reports-map',
                            style: 'mapbox://styles/mapbox/light-v10',
                            center: [-98, 38.88],
                            minZoom: 0,
                            zoom: 0
                        });

                        map.on('load', function() {
                            map.addSource('layer-source-reports', {
                                type: 'geojson',
                                data: data.geojson,
                            });

                            /* groups */
                            map.addLayer({
                                id: 'layer-groups-circle',
                                type: 'circle',
                                source: 'layer-source-reports',
                                paint: {
                                    'circle-color': '#90C741',
                                    'circle-radius': 22,
                                    'circle-stroke-width': 0.5,
                                    'circle-stroke-color': '#fff'
                                },
                                filter: ['==', 'groups', ['get', 'type'] ]
                            });
                            map.addLayer({
                                id: 'layer-groups-count',
                                type: 'symbol',
                                source: 'layer-source-reports',
                                layout: {
                                    "text-field": ['get', 'value']
                                },
                                filter: ['==', 'groups', ['get', 'type'] ]
                            });

                            /* baptism */
                            map.addLayer({
                                id: 'layer-baptisms-circle',
                                type: 'circle',
                                source: 'layer-source-reports',
                                paint: {
                                    'circle-color': '#51bbd6',
                                    'circle-radius': 22,
                                    'circle-stroke-width': 0.5,
                                    'circle-stroke-color': '#fff'
                                },
                                filter: ['==', 'baptisms', ['get', 'type'] ]
                            });
                            map.addLayer({
                                id: 'layer-baptisms-count',
                                type: 'symbol',
                                source: 'layer-source-reports',
                                layout: {
                                    "text-field": ['get', 'value']
                                },
                                filter: ['==', 'baptisms', ['get', 'type'] ]
                            });

                            spinner.removeClass('active')

                            // SET BOUNDS
                            window.map_bounds_token = 'report_activity_map'
                            window.map_start = get_map_start( window.map_bounds_token )
                            if ( window.map_start ) {
                                map.fitBounds( window.map_start, {duration: 0});
                            }
                            map.on('zoomend', function() {
                                set_map_start( window.map_bounds_token, map.getBounds() )
                            })
                            map.on('dragend', function() {
                                set_map_start( window.map_bounds_token, map.getBounds() )
                            })
                            // end set bounds
                        });

                        $('.loading-spinner').removeClass('active')

                    })
                    .fail(function(e) {
                        console.log(e)
                        $('#error').html(e)
                    })

                $('#modal-full').foundation('open')
            })
        }
        window.stream_report_buttons()



    } /* end stream app module*/
})
