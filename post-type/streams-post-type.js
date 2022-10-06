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
            ${window.lodash.escape( item.name )}
        </span>
        ${ item.status_color ? `<span class="status-square" style="background-color: ${window.lodash.escape(item.status_color)};">&nbsp;</span>` : '' }
        ${ item.update_needed && item.update_needed > 0 ? `<span>
          <img style="height: 12px;" src="${window.lodash.escape( window.wpApiShare.template_dir )}/dt-assets/images/broken.svg"/>
          <span style="font-size: 14px">${window.lodash.escape(item.update_needed)}</span>
        </span>` : '' }
      </div>`
        },
        dynamic: true,
        hint: true,
        emptyTemplate: window.lodash.escape(window.wpApiShare.translations.no_records_found),
        callback: {
            onClick: function(node, a, item){
                API.update_post( post_type, post_id, {assigned_to: 'user-' + item.ID}).then(function (response) {
                    window.lodash.set(post, "assigned_to", response.assigned_to)
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
                if (window.lodash.get(post,  "assigned_to.display")){
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
                         <div class="cell medium-5 center">
                            <div class="center">
                                <button class="button-small button" style="background-color: royalblue;" id="baptisms_report">Baptisms</button>
                                <button class="button-small button" style="background-color: orange;" id="disciples_report">Disciples</button>
                                <button class="button-small button" style="background-color: green;" id="churches_report">Churches</button>
                                <button class="button-small button hollow" id="all">All</button>
                            </div>
                            <div id="reports-map" style="width:100%;height:${window.innerHeight - 140}px;"></div>
                        </div>
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
                                     <div class="section-subheader"><span class="title-year">${window.lodash.escape( i )}</span> </div>
                                     <table class="hover"><tbody id="report-list-${window.lodash.escape( i )}"></tbody></table>
                                 </div>
                             `)
                            let inner_list = $('#report-list-'+window.lodash.escape( i ))
                            $.each(v, function(ii,vv){
                                inner_list.append(`
                                <tr><td>${window.lodash.escape( vv.value )} total ${window.lodash.escape( vv.payload.type )} in ${window.lodash.escape( vv.label )}</td><td style="vertical-align: middle;"></td></tr>
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
                                <div class="cell ">
                                    <span class="stat-heading">Total Baptisms</span>: 
                                    <span id="total_baptisms" class="stat-number">${v.total_baptisms}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Total Disciples</span>: 
                                    <span id="total_disciples" class="stat-number">${v.total_disciples}</span>
                                </div>
                                <div class="cell">
                                    <span class="stat-heading">Total Churches</span>: 
                                    <span id="total_churches" class="stat-number">${v.total_churches}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged Countries</span>: 
                                    <span class="stat-number">${v.total_countries}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged States</span>: 
                                    <span class="stat-number">${v.total_states}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged Counties</span>: 
                                    <span class="stat-number">${v.total_counties}</span>
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

                        map.dragRotate.disable();
                        map.touchZoomRotate.disableRotation();

                        map.on('load', function () {
                            map.addSource('layer-source-reports', {
                                type: 'geojson',
                                data: data.geojson,
                            });

                            /* churches */
                            map.addLayer({
                                id: 'layer-churches-circle',
                                type: 'circle',
                                source: 'layer-source-reports',
                                paint: {
                                    'circle-color': 'green',
                                    'circle-radius': {
                                        stops: [[8, 22], [11, 27], [16, 35]]
                                    },
                                    'circle-stroke-width': 0.5,
                                    'circle-stroke-color': '#fff'
                                },
                                filter: ['==', 'churches', ['get', 'type']]
                            });
                            map.addLayer({
                                id: 'layer-churches-count',
                                type: 'symbol',
                                source: 'layer-source-reports',
                                layout: {
                                    "text-field": ['get', 'value']
                                },
                                paint: {
                                    "text-color": "#ffffff"
                                },
                                filter: ['==', 'churches', ['get', 'type']]
                            });

                            /* disciples */
                            map.addLayer({
                                id: 'layer-disciples-circle',
                                type: 'circle',
                                source: 'layer-source-reports',
                                paint: {
                                    'circle-color': 'orange',
                                    'circle-radius': {
                                        stops: [[8, 20], [11, 25], [16, 28]]
                                    },
                                    'circle-stroke-width': 0.2,
                                    'circle-stroke-color': '#fff'
                                },
                                filter: ['==', 'disciples', ['get', 'type']]
                            });
                            map.addLayer({
                                id: 'layer-disciples-count',
                                type: 'symbol',
                                source: 'layer-source-reports',
                                layout: {
                                    "text-field": ['get', 'value']
                                },
                                paint: {
                                    "text-color": "#ffffff"
                                },
                                filter: ['==', 'disciples', ['get', 'type']]
                            });

                            /* baptism */
                            map.addLayer({
                                id: 'layer-baptisms-circle',
                                type: 'circle',
                                source: 'layer-source-reports',
                                paint: {
                                    'circle-color': 'royalblue',
                                    'circle-radius': {
                                        stops: [[8, 12], [11, 17], [16, 22]]
                                    },
                                    'circle-stroke-width': 0.5,
                                    'circle-stroke-color': '#fff'
                                },
                                filter: ['==', 'baptisms', ['get', 'type']]
                            });
                            map.addLayer({
                                id: 'layer-baptisms-count',
                                type: 'symbol',
                                source: 'layer-source-reports',
                                layout: {
                                    "text-field": ['get', 'value']
                                },
                                paint: {
                                    "text-color": "#ffffff"
                                },
                                filter: ['==', 'baptisms', ['get', 'type']]
                            });

                            map.setLayoutProperty('layer-baptisms-count', 'visibility', 'none');
                            map.setLayoutProperty('layer-disciples-count', 'visibility', 'none');
                            map.setLayoutProperty('layer-churches-count', 'visibility', 'none');
                            spinner.removeClass('active')

                            // SET BOUNDS
                            window.map_bounds_token = 'report_activity_map'
                            window.map_start = get_map_start(window.map_bounds_token)
                            if (window.map_start) {
                                map.fitBounds(window.map_start, {duration: 0});
                            }
                            map.on('zoomend', function () {
                                set_map_start(window.map_bounds_token, map.getBounds())
                            })
                            map.on('dragend', function () {
                                set_map_start(window.map_bounds_token, map.getBounds())
                            })
                            // end set bounds


                            jQuery('#baptisms_report').on('click', () => {
                                console.log('click')
                                hide_all()
                                map.setLayoutProperty('layer-baptisms-circle', 'visibility', 'visible');
                                map.setLayoutProperty('layer-baptisms-count', 'visibility', 'visible');
                            })
                            jQuery('#disciples_report').on('click', () => {
                                console.log('click')
                                hide_all()
                                map.setLayoutProperty('layer-disciples-circle', 'visibility', 'visible');
                                map.setLayoutProperty('layer-disciples-count', 'visibility', 'visible');
                            })
                            jQuery('#churches_report').on('click', () => {
                                console.log('click')
                                hide_all()
                                map.setLayoutProperty('layer-churches-circle', 'visibility', 'visible');
                                map.setLayoutProperty('layer-churches-count', 'visibility', 'visible');
                            })
                            jQuery('#all').on('click', () => {
                                show_all()
                            })
                        });

                        function hide_all() {
                            const layers = ['layer-baptisms-circle', 'layer-baptisms-count', 'layer-disciples-circle', 'layer-disciples-count','layer-churches-circle', 'layer-churches-count' ]
                            for( const layer_id of layers) {
                                map.setLayoutProperty( layer_id, 'visibility', 'none');
                            }
                        }
                        function show_all() {
                            hide_all()
                            const layers = ['layer-baptisms-circle', 'layer-disciples-circle', 'layer-churches-circle' ]
                            for( const layer_id of layers) {
                                map.setLayoutProperty( layer_id, 'visibility', 'visible');
                            }
                        }

                        $('.loading-spinner').removeClass('active')

                    })
                    .fail(function(e) {
                        console.log(e)
                        $('#error').html(e)
                    })

                $('#modal-full').foundation('open')
            })

            /*CHILDREN*/
            $(`#${type_settings.root}-${type_settings.type}-manage-child-reports`).on('click', function(e){
                let spinner = $('.loading-spinner')
                let title = $('#modal-full-title')
                let content = $('#modal-full-content')
                title.empty().html(`<h2>Child Reports</h2>`)
                content.empty().html(`
                    <span class="loading-spinner active"></span>
                    <div class="grid-x grid-padding-x" ">
                        <div class="cell medium-4" id="reports-list" style="height:${window.innerHeight - 80}px;overflow-y:scroll;"></div>
                        <div class="cell medium-3" id="reports-stats" style="height:${window.innerHeight - 80}px;overflow-y:scroll;"></div>
                         <div class="cell medium-5 center">
                            <div class="center">
                                <button class="button-small button" style="background-color: royalblue;" id="baptisms_report">Baptisms</button>
                                <button class="button-small button" style="background-color: orange;" id="disciples_report">Disciples</button>
                                <button class="button-small button" style="background-color: green;" id="churches_report">Churches</button>
                                <button class="button-small button hollow" id="all">All</button>
                            </div>
                            <div id="reports-map" style="width:100%;height:${window.innerHeight - 140}px;"></div>
                        </div>
                    </div>
                `)

                // get report list
                $.ajax({
                    type: "POST",
                    data: JSON.stringify({ post_id: detailsSettings.post_id } ),
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: wpApiShare.root + streams_report_module.report.root + '/v1/' + streams_report_module.report.type + '/all_children',
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
                                     <div class="section-subheader"><span class="title-year">${window.lodash.escape( i )}</span> </div>
                                     <table class="hover"><tbody id="report-list-${window.lodash.escape( i )}"></tbody></table>
                                 </div>
                             `)
                            let inner_list = $('#report-list-'+window.lodash.escape( i ))
                            $.each(v, function(ii,vv){
                                inner_list.append(`
                                <tr><td>${window.lodash.escape( vv.value )} total ${window.lodash.escape( vv.payload.type )} in ${window.lodash.escape( vv.label )} (<a href="${wpApiShare.site_url}/streams/${vv.post_id}">${vv.title}</a>)</td><td style="vertical-align: middle;"></td></tr>
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
                                <div class="cell ">
                                    <span class="stat-heading">Total Baptisms</span>: 
                                    <span id="total_baptisms" class="stat-number">${v.total_baptisms}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Total Disciples</span>: 
                                    <span id="total_disciples" class="stat-number">${v.total_disciples}</span>
                                </div>
                                <div class="cell">
                                    <span class="stat-heading">Total Churches</span>: 
                                    <span id="total_churches" class="stat-number">${v.total_churches}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged Countries</span>: 
                                    <span class="stat-number">${v.total_countries}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged States</span>: 
                                    <span class="stat-number">${v.total_states}</span>
                                </div>
                                <div class="cell ">
                                    <span class="stat-heading">Engaged Counties</span>: 
                                    <span class="stat-number">${v.total_counties}</span>
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

                        map.dragRotate.disable();
                        map.touchZoomRotate.disableRotation();

                        map.on('load', function () {
                            map.addSource('layer-source-reports', {
                                type: 'geojson',
                                data: data.geojson,
                            });

                            /* churches */
                            map.addLayer({
                                id: 'layer-churches-circle',
                                type: 'circle',
                                source: 'layer-source-reports',
                                paint: {
                                    'circle-color': 'green',
                                    'circle-radius': {
                                        stops: [[8, 22], [11, 27], [16, 35]]
                                    },
                                    'circle-stroke-width': 0.5,
                                    'circle-stroke-color': '#fff'
                                },
                                filter: ['==', 'churches', ['get', 'type']]
                            });
                            map.addLayer({
                                id: 'layer-churches-count',
                                type: 'symbol',
                                source: 'layer-source-reports',
                                layout: {
                                    "text-field": ['get', 'value']
                                },
                                paint: {
                                    "text-color": "#ffffff"
                                },
                                filter: ['==', 'churches', ['get', 'type']]
                            });

                            /* disciples */
                            map.addLayer({
                                id: 'layer-disciples-circle',
                                type: 'circle',
                                source: 'layer-source-reports',
                                paint: {
                                    'circle-color': 'orange',
                                    'circle-radius': {
                                        stops: [[8, 20], [11, 25], [16, 28]]
                                    },
                                    'circle-stroke-width': 0.2,
                                    'circle-stroke-color': '#fff'
                                },
                                filter: ['==', 'disciples', ['get', 'type']]
                            });
                            map.addLayer({
                                id: 'layer-disciples-count',
                                type: 'symbol',
                                source: 'layer-source-reports',
                                layout: {
                                    "text-field": ['get', 'value']
                                },
                                paint: {
                                    "text-color": "#ffffff"
                                },
                                filter: ['==', 'disciples', ['get', 'type']]
                            });

                            /* baptism */
                            map.addLayer({
                                id: 'layer-baptisms-circle',
                                type: 'circle',
                                source: 'layer-source-reports',
                                paint: {
                                    'circle-color': 'royalblue',
                                    'circle-radius': {
                                        stops: [[8, 12], [11, 17], [16, 22]]
                                    },
                                    'circle-stroke-width': 0.5,
                                    'circle-stroke-color': '#fff'
                                },
                                filter: ['==', 'baptisms', ['get', 'type']]
                            });
                            map.addLayer({
                                id: 'layer-baptisms-count',
                                type: 'symbol',
                                source: 'layer-source-reports',
                                layout: {
                                    "text-field": ['get', 'value']
                                },
                                paint: {
                                    "text-color": "#ffffff"
                                },
                                filter: ['==', 'baptisms', ['get', 'type']]
                            });

                            map.setLayoutProperty('layer-baptisms-count', 'visibility', 'none');
                            map.setLayoutProperty('layer-disciples-count', 'visibility', 'none');
                            map.setLayoutProperty('layer-churches-count', 'visibility', 'none');
                            spinner.removeClass('active')

                            // SET BOUNDS
                            window.map_bounds_token = 'report_activity_map'
                            window.map_start = get_map_start(window.map_bounds_token)
                            if (window.map_start) {
                                map.fitBounds(window.map_start, {duration: 0});
                            }
                            map.on('zoomend', function () {
                                set_map_start(window.map_bounds_token, map.getBounds())
                            })
                            map.on('dragend', function () {
                                set_map_start(window.map_bounds_token, map.getBounds())
                            })
                            // end set bounds


                            jQuery('#baptisms_report').on('click', () => {
                                console.log('click')
                                hide_all()
                                map.setLayoutProperty('layer-baptisms-circle', 'visibility', 'visible');
                                map.setLayoutProperty('layer-baptisms-count', 'visibility', 'visible');
                            })
                            jQuery('#disciples_report').on('click', () => {
                                console.log('click')
                                hide_all()
                                map.setLayoutProperty('layer-disciples-circle', 'visibility', 'visible');
                                map.setLayoutProperty('layer-disciples-count', 'visibility', 'visible');
                            })
                            jQuery('#churches_report').on('click', () => {
                                console.log('click')
                                hide_all()
                                map.setLayoutProperty('layer-churches-circle', 'visibility', 'visible');
                                map.setLayoutProperty('layer-churches-count', 'visibility', 'visible');
                            })
                            jQuery('#all').on('click', () => {
                                show_all()
                            })
                        });

                        function hide_all() {
                            const layers = ['layer-baptisms-circle', 'layer-baptisms-count', 'layer-disciples-circle', 'layer-disciples-count','layer-churches-circle', 'layer-churches-count' ]
                            for( const layer_id of layers) {
                                map.setLayoutProperty( layer_id, 'visibility', 'none');
                            }
                        }
                        function show_all() {
                            hide_all()
                            const layers = ['layer-baptisms-circle', 'layer-disciples-circle', 'layer-churches-circle' ]
                            for( const layer_id of layers) {
                                map.setLayoutProperty( layer_id, 'visibility', 'visible');
                            }
                        }

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
