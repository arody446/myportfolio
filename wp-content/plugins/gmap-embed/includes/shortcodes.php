<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// ************* Google Map SRM Shortcode***************
if (!function_exists('srm_gmap_embed_shortcode')) {

    function srm_gmap_embed_shortcode($atts, $content)
    {
        static $count;
        if (!$count) {
            $count = 0;
        }
        $count++;

        $wpgmap_title = esc_html(get_post_meta($atts['id'], 'wpgmap_title', true));
        $wpgmap_show_heading = esc_html(get_post_meta($atts['id'], 'wpgmap_show_heading', true));
        $wpgmap_heading_class = esc_html(get_post_meta($atts['id'], 'wpgmap_heading_class', true));
        $wpgmap_latlng = esc_html(get_post_meta($atts['id'], 'wpgmap_latlng', true));
        $wpgmap_disable_zoom_scroll = esc_html(get_post_meta($atts['id'], 'wpgmap_disable_zoom_scroll', true));
        $wpgmap_map_zoom = esc_html(get_post_meta($atts['id'], 'wpgmap_map_zoom', true));
        $wpgmap_map_width = esc_html(get_post_meta($atts['id'], 'wpgmap_map_width', true));
        $wpgmap_map_height = esc_html(get_post_meta($atts['id'], 'wpgmap_map_height', true));
        $wpgmap_map_type = esc_html(get_post_meta($atts['id'], 'wpgmap_map_type', true));
        $wpgmap_map_address = esc_html(get_post_meta($atts['id'], 'wpgmap_map_address', true));
        $wpgmap_show_infowindow = get_post_meta($atts['id'], 'wpgmap_show_infowindow', true);

        ob_start();

        if (isset($wpgmap_show_heading) && $wpgmap_show_heading == 1) {
            echo "<h1 class='$wpgmap_heading_class'>" . $wpgmap_title . "</h1>";
        }
        ?>
        <script type="text/javascript">
            google.maps.event.addDomListener(window, 'load', function () {
                var map = new google.maps.Map(document.getElementById("srm_gmp_embed_<?php echo $count; ?>"), {
                    center: new google.maps.LatLng(<?php echo $wpgmap_latlng;?>),
                    zoom:<?php echo $wpgmap_map_zoom;?>,
                    mapTypeId: google.maps.MapTypeId.<?php echo $wpgmap_map_type;?>,
                    scrollwheel:'<?php echo $wpgmap_disable_zoom_scroll==1?false:true;?>'
                });

                var marker = new google.maps.Marker({
                    position: new google.maps.LatLng(<?php echo $wpgmap_latlng;?>),
                    map: map,
                    animation: google.maps.Animation.DROP
                });
                marker.setMap(map);

                <?php
                if($wpgmap_show_infowindow){
                ?>
                var infowindow = new google.maps.InfoWindow({
                    content: "<?php echo $wpgmap_map_address;?>"
                });

                infowindow.open(map, marker);

                google.maps.event.addListener(marker, 'click', function () {
                    infowindow.open(map, marker);
                });

                <?php
                }
                ?>

            });
        </script>

        <div id="srm_gmp_embed_<?php echo $count; ?>"
             style="width:<?php echo $wpgmap_map_width . ' !important'; ?>;height:<?php echo $wpgmap_map_height; ?>  !important;margin:5px 0; ">

        </div>
        <?php
        return ob_get_clean();
    }

}

//******* Define Shortcode for Google Map SRM
add_shortcode('gmap-embed', 'srm_gmap_embed_shortcode');