
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue Font Awesome zodat de iconen zichtbaar zijn.
 */
add_action( 'wp_enqueue_scripts', 'enqueue_font_awesome_for_orders_overzicht' );
function enqueue_font_awesome_for_orders_overzicht() {
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' );
}

/**
 * Nieuwe gebruiker aanmaken via de popup.
 * Wanneer deze popup wordt ingezonden, wordt een nieuwe gebruiker aangemaakt.
 */
$new_user_created = false;
$new_user_data = array(
    'first_name' => '',
    'last_name'  => '',
    'company'    => '',
    'street'     => '',
    'postcode'   => '',
    'city'       => '',
    'email'      => '',
    'phone'      => '',
);
if ( isset($_POST['new_user_submit']) ) {
    // Verzamel en sanitiseer invoer
    $first_name = sanitize_text_field( $_POST['new_first_name'] );
    $last_name  = sanitize_text_field( $_POST['new_last_name'] );
    $company    = isset($_POST['new_company']) ? sanitize_text_field( $_POST['new_company'] ) : '';
    $street     = isset($_POST['new_street']) ? sanitize_text_field( $_POST['new_street'] ) : '';
    $postcode   = isset($_POST['new_postcode']) ? sanitize_text_field( $_POST['new_postcode'] ) : '';
    $city       = isset($_POST['new_city']) ? sanitize_text_field( $_POST['new_city'] ) : '';
    $email      = sanitize_email( $_POST['new_email'] );
    $phone      = isset($_POST['new_phone']) ? sanitize_text_field( $_POST['new_phone'] ) : '';

    // Bewaar ingevulde waarden voor later in de popup
    $new_user_data = array(
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'company'    => $company,
        'street'     => $street,
        'postcode'   => $postcode,
        'city'       => $city,
        'email'      => $email,
        'phone'      => $phone,
    );

    // Als er geen e-mail is ingevuld, genereer dan een dummy-e-mail (WordPress vereist een e-mail)
    if ( empty( $email ) ) {
         $name_part = trim($first_name . $last_name);
         if ( empty( $name_part ) ) {
             $name_part = 'user';
         }
         $email = strtolower( preg_replace('/\s+/', '', $name_part) ) . '-' . time() . '@example.com';
    }

    // Bepaal gebruikersnaam op basis van het gedeelte voor '@'
    $username_base = strstr( $email, '@', true );
    if ( empty( $username_base ) ) {
        $username_base = 'user';
    }
    $username = $username_base;
    $counter = 1;
    while ( username_exists( $username ) ) {
        $username = $username_base . $counter;
        $counter++;
    }

    // Maak een willekeurig wachtwoord aan
    $password = wp_generate_password( 12, false );

    // Maak de gebruiker aan
    $userdata = array(
        'user_login' => $username,
        'user_email' => $email,
        'user_pass'  => $password,
        'first_name' => $first_name,
        'last_name'  => $last_name,
    );
    $user_id = wp_insert_user( $userdata );

    if ( is_wp_error( $user_id ) ) {
        wp_die("Er is een fout opgetreden: " . $user_id->get_error_message());
    } else {
        if ( ! empty( $company ) ) {
            update_user_meta( $user_id, 'billing_company', $company );
        }
        if ( ! empty( $street ) ) {
            update_user_meta( $user_id, 'billing_address_1', $street );
        }
        if ( ! empty( $postcode ) ) {
            update_user_meta( $user_id, 'billing_postcode', $postcode );
        }
        if ( ! empty( $city ) ) {
            update_user_meta( $user_id, 'billing_city', $city );
        }
        if ( ! empty( $phone ) ) {
            update_user_meta( $user_id, 'billing_phone', $phone );
        }
        $new_user_created = true;
    }
}

/**
 * Voeg meta-tags toe zodat Apple de pagina als een standalone web app ziet,
 * de pagina niet geïndexeerd wordt en inzoomen op mobiel wordt geblokkeerd.
 */
add_action('wp_head', 'add_apple_web_app_meta_tags');
function add_apple_web_app_meta_tags() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black">' . "\n";
    echo '<meta name="robots" content="noindex">' . "\n";
}

/**
 * Forceer in de admin dat we 'page=week-overzicht' zetten
 * als er filterparameters aanwezig zijn maar de parameter 'page' ontbreekt of verkeerd is.
 */
add_action( 'admin_init', 'fix_backend_filters_white_page' );
function fix_backend_filters_white_page() {
    if ( is_admin() ) {
        $filter_params = array_intersect_key( $_GET, array(
            'weeks_ahead'    => '',
            'day'            => '',
            'tag'            => '',
            'show_cancelled' => '',
            'custom_from'    => '',
            'custom_to'      => '',
            'combined_filter'=> '',
        ) );
        if ( ! empty( $filter_params ) ) {
            $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
            if ( $current_page !== 'week-overzicht' ) {
                $args = $_GET;
                $args['page'] = 'week-overzicht';
                wp_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
                exit;
            }
        }
    }
}

/**
 * Helperfunctie om het $_FILES-array te herschikken voor meerdere uploads.
 */
function restructure_files_array($file_post) {
    $files = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);
    for ($i = 0; $i < $file_count; $i++) {
         foreach($file_keys as $key) {
              $files[$i][$key] = $file_post[$key][$i];
         }
    }
    return $files;
}

/**
 * JavaScript-functie om extra PDF uploadblokken toe te voegen
 */
?>
<script>
function addPdfUploadBlock(containerId) {
    var container = document.getElementById(containerId);
    var block = document.createElement('div');
    block.className = 'pdf_upload_block';
    block.style.marginBottom = '10px';
    block.innerHTML = '<input type="file" name="custom_pdf_documents[]" accept="application/pdf"> <select name="custom_pdf_visibility[]"><option value="public">Openbaar</option><option value="private">Privé</option></select>';
    container.appendChild(block);
}
</script>
<?php

/**
 * Genereer het volledige overzicht van WooCommerce-orders en custom events.
 *
 * Ondersteunt:
 * 1. Toevoegen, bijwerken en verwijderen van custom events.
 * 2. Filteren op week, dag, zoekterm en een custom periode (van/tot) zoals geselecteerd.
 *    (Standaard wordt de huidige week getoond.)
 * 3. Alleen orders en events binnen de gekozen periode tonen.
 * 4. Een gecombineerde filter (eventtype) via één dropdown.
 *    Mogelijke waarden:
 *       - Leeg: Alle events (zowel eigen als orders)
 *       - "eigen": Alleen eigen events (maatwerk)
 * 5. Bij maatwerk wordt het extra veld "Party Rental Besteld" getoond.
 * 6. PDF-documenten kunnen worden toegevoegd en verwijderd.
 *
 * @param bool $is_shortcode Indien true (frontend) wordt de weekfilter genegeerd bij een zoekterm.
 * @return string HTML-output.
 */
function get_orders_current_week_output( $is_shortcode = false ) {

    // Beperk toegang: gebruikers met de rol "klant" krijgen geen toegang
    $current_user = wp_get_current_user();
    if ( in_array( 'klant', (array) $current_user->roles ) ) {
        wp_die("U heeft geen toegang tot deze pagina.");
    }

    // Haal custom events op (éénmalig)
    $custom_events = get_option('custom_events', array());

    /* --- Custom event: Toevoegen --- */
    if ( isset($_POST['custom_event_submit']) ) {
        $event_number = sanitize_text_field( $_POST['custom_event_number'] );
        $event_id = ! empty($event_number) ? $event_number : ( time() . rand(100,999) );
        $event = array(
            'event_id'                    => $event_id,
            'status'                      => sanitize_text_field( $_POST['custom_status'] ),
            'option_date'                 => isset($_POST['custom_option_date']) ? sanitize_text_field( $_POST['custom_option_date'] ) : '',
            'event_number'                => $event_number,
            'first_name'                  => sanitize_text_field( $_POST['custom_first_name'] ),
            'last_name'                   => sanitize_text_field( $_POST['custom_last_name'] ),
            'company'                     => sanitize_text_field( $_POST['custom_company'] ),
            'address'                     => sanitize_text_field( $_POST['custom_address'] ),
            'postcode'                    => sanitize_text_field( $_POST['custom_postcode'] ),
            'city'                        => sanitize_text_field( $_POST['custom_city'] ),
            'email'                       => sanitize_email( $_POST['custom_email'] ),
            'phone'                       => sanitize_text_field( $_POST['custom_phone'] ),
            'reference'                   => isset($_POST['custom_reference']) ? sanitize_text_field( $_POST['custom_reference'] ) : '',
            'date'                        => sanitize_text_field( $_POST['custom_date'] ),
            'start_time'                  => sanitize_text_field( $_POST['custom_start_time'] ),
            'end_time'                    => sanitize_text_field( $_POST['custom_end_time'] ),
            'note'                        => sanitize_textarea_field( $_POST['custom_note'] ),
            'staff'                       => sanitize_text_field( $_POST['custom_staff'] ),
            'personen'                    => isset($_POST['custom_personen']) ? sanitize_text_field( $_POST['custom_personen'] ) : '',
            'custom_party_rental_besteld' => isset($_POST['custom_party_rental_besteld']) ? sanitize_text_field( $_POST['custom_party_rental_besteld'] ) : '',
        );
        // Logboek: wie maakt het event aan?
        $event['created_by'] = get_current_user_id();
        $event['last_modified_by'] = get_current_user_id();
        $event['created_at'] = current_time('mysql');
        $event['last_modified_at'] = current_time('mysql');
        
        $pdfs = array();
        if ( ! empty($_FILES['custom_pdf_documents']['name'][0]) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            $uploaded_files = $_FILES['custom_pdf_documents'];
            $files = restructure_files_array($uploaded_files);
            $pdf_visibility = isset($_POST['custom_pdf_visibility']) ? $_POST['custom_pdf_visibility'] : array();
            foreach ($files as $index => $file) {
                 $upload_overrides = array('test_form' => false);
                 $movefile = wp_handle_upload( $file, $upload_overrides );
                 if ( $movefile && !isset($movefile['error']) ) {
                      $visibility = isset($pdf_visibility[$index]) ? $pdf_visibility[$index] : 'public';
                      $pdfs[] = array(
                          'url'         => $movefile['url'],
                          'upload_date' => date('d/m/Y'),
                          'visibility'  => $visibility,
                      );
                 }
            }
        }
        $event['pdf_documents'] = $pdfs;
        $custom_events[$event_id] = $event;
        update_option('custom_events', $custom_events);
        $cache_key = 'ocw_output_' . md5( serialize($_GET) );
        delete_transient( $cache_key );
        wp_redirect( remove_query_arg( array('custom_event_submit') ) );
        exit;
    }

    /* --- Custom event: Updaten --- */
    if ( isset($_POST['custom_event_update']) ) {
        $event_id = sanitize_text_field( $_POST['custom_event_id'] );
        if ( isset($custom_events[$event_id]) ) {
            $event = array(
                'event_id'                    => $event_id,
                'status'                      => sanitize_text_field( $_POST['custom_status'] ),
                'option_date'                 => isset($_POST['custom_option_date']) ? sanitize_text_field( $_POST['custom_option_date'] ) : '',
                'event_number'                => sanitize_text_field( $_POST['custom_event_number'] ),
                'first_name'                  => sanitize_text_field( $_POST['custom_first_name'] ),
                'last_name'                   => sanitize_text_field( $_POST['custom_last_name'] ),
                'company'                     => sanitize_text_field( $_POST['custom_company'] ),
                'address'                     => sanitize_text_field( $_POST['custom_address'] ),
                'postcode'                    => sanitize_text_field( $_POST['custom_postcode'] ),
                'city'                        => sanitize_text_field( $_POST['custom_city'] ),
                'email'                       => sanitize_email( $_POST['custom_email'] ),
                'phone'                       => sanitize_text_field( $_POST['custom_phone'] ),
                'reference'                   => isset($_POST['custom_reference']) ? sanitize_text_field( $_POST['custom_reference'] ) : '',
                'date'                        => sanitize_text_field( $_POST['custom_date'] ),
                'start_time'                  => sanitize_text_field( $_POST['custom_start_time'] ),
                'end_time'                    => sanitize_text_field( $_POST['custom_end_time'] ),
                'note'                        => sanitize_textarea_field( $_POST['custom_note'] ),
                'staff'                       => sanitize_text_field( $_POST['custom_staff'] ),
                'personen'                    => isset($_POST['custom_personen']) ? sanitize_text_field( $_POST['custom_personen'] ) : '',
                'custom_party_rental_besteld' => isset($_POST['custom_party_rental_besteld']) ? sanitize_text_field( $_POST['custom_party_rental_besteld'] ) : '',
            );
            // Update logboek: alleen last modified aanpassen
            $event['last_modified_by'] = get_current_user_id();
            $event['last_modified_at'] = current_time('mysql');
            
            $pdfs = isset($custom_events[$event_id]['pdf_documents']) ? $custom_events[$event_id]['pdf_documents'] : array();
            if ( ! empty($_FILES['custom_pdf_documents']['name'][0]) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                $uploaded_files = $_FILES['custom_pdf_documents'];
                $files = restructure_files_array($uploaded_files);
                $pdf_visibility = isset($_POST['custom_pdf_visibility']) ? $_POST['custom_pdf_visibility'] : array();
                foreach ($files as $index => $file) {
                     $upload_overrides = array('test_form' => false);
                     $movefile = wp_handle_upload( $file, $upload_overrides );
                     if ( $movefile && !isset($movefile['error']) ) {
                          $visibility = isset($pdf_visibility[$index]) ? $pdf_visibility[$index] : 'public';
                          $pdfs[] = array(
                              'url'         => $movefile['url'],
                              'upload_date' => date('d/m/Y'),
                              'visibility'  => $visibility,
                          );
                     }
                }
            }
            $event['pdf_documents'] = $pdfs;
            $custom_events[$event_id] = $event;
            update_option('custom_events', $custom_events);
            $cache_key = 'ocw_output_' . md5( serialize($_GET) );
            delete_transient( $cache_key );
        }
        wp_redirect( remove_query_arg( array('custom_event_update') ) );
        exit;
    }

    /* --- Custom event: Verwijderen --- */
    if ( isset($_GET['delete_event']) ) {
        // Alleen beheerder mag verwijderen
        if ( current_user_can('manage_options') ) {
            $event_id = sanitize_text_field( $_GET['delete_event'] );
            if ( isset($custom_events[$event_id]) ) {
                unset($custom_events[$event_id]);
                update_option('custom_events', $custom_events);
                $cache_key = 'ocw_output_' . md5( serialize($_GET) );
                delete_transient( $cache_key );
                wp_redirect( remove_query_arg('delete_event') );
                exit;
            }
        }
    }
    
    /* --- Custom event: PDF verwijderen --- */
    if ( isset($_GET['delete_pdf_event']) && isset($_GET['delete_pdf_index']) ) {
        // Alleen beheerder mag PDF's verwijderen
        if ( current_user_can('manage_options') ) {
            $event_id = sanitize_text_field( $_GET['delete_pdf_event'] );
            $pdf_index = intval( $_GET['delete_pdf_index'] );
            if ( isset($custom_events[$event_id]) && isset($custom_events[$event_id]['pdf_documents'][$pdf_index]) ) {
                unset($custom_events[$event_id]['pdf_documents'][$pdf_index]);
                $custom_events[$event_id]['pdf_documents'] = array_values($custom_events[$event_id]['pdf_documents']);
                update_option('custom_events', $custom_events);
                if ( is_admin() ) {
                    wp_redirect( admin_url('admin.php?page=week-overzicht') );
                } else {
                    wp_redirect( remove_query_arg( array('delete_pdf_event','delete_pdf_index') ) );
                }
                exit;
            }
        }
    }
    
    // Verkrijg de week-offset, dag-, zoek- en custom datum filter
    $weeks_ahead  = isset( $_GET['weeks_ahead'] ) ? intval( $_GET['weeks_ahead'] ) : 0;
    $filter_day   = isset( $_GET['day'] )         ? sanitize_text_field( $_GET['day'] ) : '';
    $filter_order = isset( $_GET['order_search'] ) ? sanitize_text_field( $_GET['order_search'] ) : '';
    
    // Custom datum filter: gebruik de door de gebruiker gespecificeerde periode (anders huidige week met offset)
    if ( isset($_GET['custom_from']) && isset($_GET['custom_to']) && !empty($_GET['custom_from']) && !empty($_GET['custom_to']) ) {
        $start_date = sanitize_text_field( $_GET['custom_from'] );
        $end_date   = sanitize_text_field( $_GET['custom_to'] );
    } else {
        $start_date = date( 'Y-m-d', strtotime( 'monday this week + ' . $weeks_ahead . ' weeks' ) );
        $end_date   = date( 'Y-m-d', strtotime( 'friday this week + ' . $weeks_ahead . ' weeks' ) );
    }
    $week_number = date( 'W', strtotime( $start_date ) );

    // De gecombineerde filter via dropdown:
    // Mogelijke waarden: "", "eigen"
    $combined_filter = isset($_GET['combined_filter']) ? sanitize_text_field( $_GET['combined_filter'] ) : '';

    $show_cancelled = ! isset( $_GET['hide_cancelled'] );
    $post_statuses = $show_cancelled
        ? array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-cancelled' )
        : array( 'wc-completed', 'wc-processing', 'wc-on-hold' );
    $day_map = array(
        1 => 'maandag',
        2 => 'dinsdag',
        3 => 'woensdag',
        4 => 'donderdag',
        5 => 'vrijdag',
        6 => 'zaterdag',
        7 => 'zondag',
    );

    // Verwerk WooCommerce-orders
    if ( empty($filter_order) ) {
        $args = array(
            'post_type'      => 'shop_order',
            'post_status'    => $post_statuses,
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'meta_query'     => array(
                array(
                    'key'     => 'pi_system_delivery_date',
                    'value'   => array( $start_date, $end_date ),
                    'type'    => 'DATE',
                    'compare' => 'BETWEEN',
                ),
            ),
            'meta_key'       => 'pi_system_delivery_date',
            'fields'         => 'ids',
        );
    } else {
        // Als er een zoekterm is, haal alle orders op (datumfilter weghalen)
        $args = array(
            'post_type'      => 'shop_order',
            'post_status'    => $post_statuses,
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'meta_key'       => 'pi_system_delivery_date',
            'fields'         => 'ids',
        );
    }
    $orders_query = new WP_Query( $args );
    $order_list = array();
    if ( $orders_query->have_posts() ) {
        foreach ( $orders_query->posts as $o_id ) {
            $order = wc_get_order( $o_id );
            if ( ! $order ) continue;
            $order_date = get_post_meta( $order->get_id(), 'pi_system_delivery_date', true );
            if ( ! $order_date ) continue;
            // Alleen toepassen als er geen zoekterm is
            if ( empty($filter_order) ) {
                if ( strtotime($order_date) < strtotime($start_date) || strtotime($order_date) > strtotime($end_date) ) {
                    continue;
                }
            }
            // Indien er een zoekterm is, filter order op nummer, referentie of bedrijfsnaam
            if ( ! empty($filter_order) ) {
                $order_number = $order->get_order_number();
                $order_reference = get_post_meta( $order->get_id(), 'order_reference', true );
                $billing_company = $order->get_billing_company();
                if (
                    stripos($order_number, $filter_order) === false &&
                    stripos($order_reference, $filter_order) === false &&
                    stripos($billing_company, $filter_order) === false
                ) {
                    continue;
                }
            }
            $order_list[] = $order;
        }
    }
    
    // Verwerk custom events (datumfilter alleen toepassen als er geen zoekterm is; zoekterm filter altijd toepassen)
    $custom_events = array_filter($custom_events, function($event) use ($start_date, $end_date, $filter_order) {
        if ( empty($event['date']) ) return false;
        if ( empty($filter_order) && (strtotime($event['date']) < strtotime($start_date) || strtotime($event['date']) > strtotime($end_date)) ) return false;
        if ( ! empty($filter_order) ) {
            return (stripos($event['event_number'], $filter_order) !== false ||
                    stripos($event['reference'], $filter_order) !== false ||
                    stripos($event['company'], $filter_order) !== false);
        }
        return true;
    });
    
    /* --- Combineer WooCommerce-orders en custom events --- */
    $combined = array();
    foreach ( $order_list as $order ) {
        $date = get_post_meta( $order->get_id(), 'pi_system_delivery_date', true );
        $combined[] = array( 'type' => 'order', 'date' => $date, 'data' => $order );
    }
    foreach ( $custom_events as $event ) {
        $combined[] = array( 'type' => 'event', 'date' => $event['date'], 'data' => $event );
    }
    
    // Pas dagfilter toe indien ingesteld
    if ( ! empty($filter_day) ) {
        $combined = array_filter($combined, function($item) use ($filter_day) {
            $day = strtolower(date_i18n('l', strtotime($item['date'])));
            return ($day === strtolower($filter_day));
        });
    }
    
    // Als er een gecombineerde filter is ('eigen') toon dan alleen maatwerk events
    if ( ! empty($combined_filter) ) {
        if ( $combined_filter === 'eigen' ) {
            $combined = array_filter($combined, function($item) {
                return ($item['type'] === 'event');
            });
        }
    }
    
    // Sorteer de gecombineerde lijst:
    // Als er een zoekterm is, sorteer dan aflopend (nieuwste eerst)
    // Anders sorteren we oplopend (vroegste naar laatste)
    if ( ! empty($filter_order) ) {
        usort($combined, function($a, $b) {
            $dateDiff = strtotime($b['date']) - strtotime($a['date']);
            if ($dateDiff === 0) {
                $timeA = ($a['type'] === 'order') ? strtotime($a['data']->get_meta('pi_delivery_time')) : strtotime($a['data']['start_time']);
                $timeB = ($b['type'] === 'order') ? strtotime($b['data']->get_meta('pi_delivery_time')) : strtotime($b['data']['start_time']);
                return $timeB - $timeA;
            }
            return $dateDiff;
        });
    } else {
        usort($combined, function($a, $b) {
            $dateDiff = strtotime($a['date']) - strtotime($b['date']);
            if ($dateDiff === 0) {
                $timeA = ($a['type'] === 'order') ? strtotime($a['data']->get_meta('pi_delivery_time')) : strtotime($a['data']['start_time']);
                $timeB = ($b['type'] === 'order') ? strtotime($b['data']->get_meta('pi_delivery_time')) : strtotime($b['data']['start_time']);
                return $timeA - $timeB;
            }
            return $dateDiff;
        });
    }
    
    ob_start();
    ?>
    <!-- CSS Styling -->
    <style>
        body { font-family: Arial, sans-serif; color: #f1f1f1; }
        html, body { -webkit-touch-callout: none; -webkit-user-select: none; }
        .filter-bar select, .filter-bar input, .filter-bar option, .filter-bar a { color: #fff !important; }
        .orders-container { margin-top: 20px; }
        .order-card { background-color: #2e2e3a; border-radius: 8px; padding: 15px; margin-bottom: 20px; position: relative; }
        .order-card-header { display: flex; align-items: center; justify-content: space-between; }
        .order-badge { border-radius: 4px; padding: 5px 10px; font-weight: bold; }
        .order-badge.default { background-color: #009640; }
        .order-badge.cancelled { background-color: #ff0000; }
        .order-badge.event { color: #fff; }
        .order-badge.event.event-in-optie { background-color: #FFA500; }
        .order-badge.event.event-akkoord { background-color: #009640; }
        /* Aangepast: event geannuleerd (maatwerk) wordt rood */
        .order-badge.event.event-geannuleerd { background-color: #ff0000; }
        .option-badge { background-color: #4f9d9d; border-radius: 12px; padding: 2px 6px; font-size: 0.7em; font-weight: bold; color: #fff; margin-left: 5px; }
        .option-badge.expired { background-color: #ff0000; }
        .action-buttons { display: flex; gap: 5px; margin-left: auto; }
        .pdf-button, .quick-button, .details-button, .edit-button, .delete-button { cursor: pointer; border: 1px solid #009640; padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 0.9em; text-decoration: none; background-color: transparent; color: #fff; transition: background-color 0.3s, color 0.3s; }
        .pdf-button:hover, .quick-button:hover, .details-button:hover, .edit-button:hover { background-color: #009640; }
        .delete-button { border: 1px solid red; background-color: red; color: #fff; }
        .delete-button:hover { background-color: darkred; }
        .order-card-body { margin-top: 10px; }
        .order-card-body div { margin-bottom: 10px; }
        .filter-bar { margin: 20px 0; background-color: #2e2e3a; padding: 10px; border-radius: 4px; }
        .filter-bar form { display: flex; flex-direction: column; gap: 10px; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-row label { font-weight: bold; color: #fff; }
        .filter-row select, .filter-row input[type="text"], .filter-row input[type="date"] { background-color: #1e1e2d; color: #fff; border: 1px solid #444; border-radius: 4px; padding: 5px 8px; font-weight: bold; transition: background-color 0.2s, color 0.2s; }
        .filter-row select:hover, .filter-row select:focus, .filter-row input[type="text"]:hover, .filter-row input[type="text"]:focus, .filter-row input[type="date"]:hover, .filter-row input[type="date"]:focus { background-color: #444; color: #fff; outline: none; }
        .filter-row select option { background-color: #1e1e2d; color: #fff; }
        .cancelled-toggle-button { background-color: #ff0000; border: 1px solid #ff0000; padding: 6px 12px; border-radius: 10px; text-decoration: none; font-weight: bold; transition: background-color 0.3s; font-size: 0.9em; color: #fff; }
        .cancelled-toggle-button:hover { background-color: #ff4040; }
        .reset-filters-button { background-color: #808080; border: 1px solid #808080; padding: 6px 12px; border-radius: 10px; text-decoration: none; font-weight: bold; transition: background-color 0.3s; font-size: 0.9em; color: #fff; }
        .reset-filters-button:hover { background-color: #696969; }
        /* Nieuwe styling voor knoppen naast elkaar in de filterbar */
        .filter-buttons { display: flex; gap: 10px; justify-content: flex-end; align-items: center; flex-wrap: wrap; }
        .navigation-buttons { text-align: center; margin-top: 20px; }
        .navigation-buttons a { margin: 0 auto; display: inline-block; background-color: #009640; border: 1px solid #009640; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: bold; transition: background-color 0.3s, color 0.3s; color: #fff; }
        .navigation-buttons a:hover { background-color: #006630; }
        .popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 9999; }
        .popup-content { background-color: #1e1e2d; padding: 30px; border-radius: 8px; width: 90%; max-width: 800px; box-shadow: 0 4px 8px rgba(0,0,0,0.5); color: #fff; max-height: 90vh; overflow-y: auto; text-align: left; }
        .popup-content h3 { color: #009640; font-weight: bold; }
        .close-popup { cursor: pointer; float: right; font-weight: bold; font-size: 20px; color: #fff; }
        #loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; }
        #loading-overlay .spinner { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 3em; color: #fff; }
        @media (max-width: 1024px) {
            .edit-button, .delete-button { display: none !important; }
        }
        @media (max-width: 768px) {
            .filter-bar form { font-size: 14px; }
            .navigation-buttons a { padding: 8px 12px; font-size: 14px; }
            .popup-content { width: 95%; max-width: 95%; padding: 15px; }
        }
        .wrap h1 { color: #fff; }
        .order-details, .event-details { display: none; }
        .event-form-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
        .event-form-group > div { flex: 1 1 48%; }
        .event-form-group.full > div { flex: 1 1 100%; }
        .event-form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .event-form-group input, .event-form-group textarea, .event-form-group select {
            width: 100%; padding: 8px; border: 1px solid #fff; border-radius: 4px;
            background-color: #333; color: #fff;
        }
        input, textarea, select {
            background-color: #333 !important; color: #fff !important; border: 1px solid #fff !important;
        }
        .form-section-title {
            width: 100%; padding: 10px; background-color: #444; border-radius: 4px;
            margin-bottom: 10px; font-weight: bold; text-align: center;
        }
        .splitter { border-bottom: 1px solid #444; margin: 15px 0; }
        .reference-block { font-weight: normal; text-align: left; margin-bottom: 10px; }
        .user-selector-container { border: 1px solid #444; border-radius: 4px; padding: 8px; margin-bottom: 10px; background-color: #222; }
        .btn-add-user { background-color: #0073aa; border: none; color: #fff; padding: 6px 12px; border-radius: 4px; margin-top: 5px; cursor: pointer; transition: background-color 0.3s; }
        .btn-add-user:hover { background-color: #005177; }
        .pdf_upload_block { margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .add-pdf-button { background-color: #28a745; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.3s; margin-top: 5px; }
        .add-pdf-button:hover { background-color: #218838; }
        .popup-product-row {
            border-bottom: 1px solid #ccc;
            padding: 5px 0;
            display: flex;
            justify-content: space-between;
        }
        .logboek {
            background-color: #444;
            color: #ccc;
            padding: 5px 10px;
            font-size: 0.8em;
            text-align: left;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>
    
    <div id="loading-overlay">
        <div class="spinner"><i class="fa fa-spinner fa-spin"></i></div>
    </div>
    
    <div id="popup-overlay" class="popup-overlay">
        <div id="popup-content" class="popup-content"></div>
    </div>
    
    <script>
    function openNewUserPopup(){
        var popup = document.getElementById('new-user-popup');
        if(popup){ popup.style.display = 'flex'; }
    }
    function closeNewUserPopup(){
        var popup = document.getElementById('new-user-popup');
        if(popup){ popup.style.display = 'none'; }
    }
    function addPdfUploadBlock(containerId) {
        var container = document.getElementById(containerId);
        var block = document.createElement('div');
        block.className = 'pdf_upload_block';
        block.innerHTML = '<input type="file" name="custom_pdf_documents[]" accept="application/pdf"> <select name="custom_pdf_visibility[]"><option value="public">Openbaar</option><option value="private">Privé</option></select>';
        container.appendChild(block);
    }
    document.addEventListener('DOMContentLoaded', function(){
        var filterForm = document.getElementById('order-filter-form');
        if(filterForm){
            filterForm.addEventListener('submit', function(){
                document.getElementById('loading-overlay').style.display = 'block';
            });
        }
        var navLinks = document.querySelectorAll('.navigation-buttons a, .cancelled-toggle-button, .reset-filters-button');
        navLinks.forEach(function(link){
            link.addEventListener('click', function(){
                document.getElementById('loading-overlay').style.display = 'block';
            });
        });
    });
    function openPopup(orderId) {
        var detailsElem = document.getElementById('details-' + orderId);
        if (detailsElem) {
            document.getElementById('popup-content').innerHTML = '<span class="close-popup" onclick="closePopup()">&times;</span>' + detailsElem.innerHTML;
            document.getElementById('popup-overlay').style.display = 'flex';
        } else {
            console.error("Details voor order " + orderId + " niet gevonden.");
        }
    }
    function openPopupEvent(eventId) {
        var detailsElem = document.getElementById('event-details-' + eventId);
        if (detailsElem) {
            document.getElementById('popup-content').innerHTML = '<span class="close-popup" onclick="closePopup()">&times;</span>' + detailsElem.innerHTML;
            document.getElementById('popup-overlay').style.display = 'flex';
        } else {
            console.error("Details voor event " + eventId + " niet gevonden.");
        }
    }
    function closePopup() {
        document.getElementById('popup-overlay').style.display = 'none';
    }
    function openEventPopup() {
        document.getElementById('event-popup-overlay').style.display = 'flex';
    }
    function closeEventPopup() {
        document.getElementById('event-popup-overlay').style.display = 'none';
    }
    function openEventDetails(eventId) {
        document.getElementById('event-details-popup-overlay').style.display = 'flex';
        var detailsContent = document.getElementById('event-details-' + eventId).innerHTML;
        document.getElementById('event-details-popup-content').innerHTML =
            '<span class="close-popup" onclick="closeEventDetails()">&times;</span>' + detailsContent;
    }
    function closeEventDetails() {
        document.getElementById('event-details-popup-overlay').style.display = 'none';
    }
    function openEditEvent(eventId) {
        var editElement = document.getElementById('event-edit-' + eventId);
        if (editElement) {
            document.getElementById('edit-event-popup-overlay').style.display = 'flex';
            var editContent = editElement.innerHTML;
            document.getElementById('edit-event-popup-content').innerHTML =
                '<span class="close-popup" onclick="closeEditEvent()">&times;</span>' + editContent;
        } else {
            console.error("Edit element voor event " + eventId + " niet gevonden.");
        }
    }
    function closeEditEvent() {
        document.getElementById('edit-event-popup-overlay').style.display = 'none';
    }
    function toggleOptionDate(val, containerId) {
        var container = document.getElementById(containerId);
        container.style.display = (val === "in optie") ? 'block' : 'none';
    }
    </script>
    
    <?php
    if ( empty( $filter_order ) ) {
        echo "<div class='week-header' style='text-align:center; font-size:18px; font-weight:bold; margin-bottom:20px;'>";
        echo "Periode: " . esc_html($start_date) . " t/m " . esc_html($end_date);
        echo "</div>";
    } else {
        echo "<div class='week-header' style='text-align:center; font-size:18px; font-weight:bold; margin-bottom:20px;'>Zoekresultaten</div>";
    }
    echo "<div class='filter-bar'>";
    ?>
    <form method="get" id="order-filter-form" style="display: flex; flex-direction: column; gap: 10px;">
        <input type="hidden" name="weeks_ahead" value="<?php echo esc_attr( $weeks_ahead ); ?>" />
        <!-- Min attributen verwijderd zodat filtering op verleden datums mogelijk is -->
        <input type="hidden" name="custom_from" value="<?php echo isset($_GET['custom_from']) ? esc_attr($_GET['custom_from']) : ''; ?>" />
        <input type="hidden" name="custom_to" value="<?php echo isset($_GET['custom_to']) ? esc_attr($_GET['custom_to']) : ''; ?>" />
        <?php if ( ! $show_cancelled ) : ?>
            <input type="hidden" name="hide_cancelled" value="1" />
        <?php endif; ?>
        <div class="filter-row">
            <input type="text" id="order-search" name="order_search" value="<?php echo esc_attr( $filter_order ); ?>" placeholder="Zoek op order-, eventnummer, referentie of bedrijfsnaam" style="flex: 1; width: 100%;" />
        </div>
        <div class="filter-row">
            <div>
                <label>Dag:</label>
                <select id="day-select" name="day">
                    <option value="">Alle dagen</option>
                    <?php
                    foreach ( $day_map as $num => $day_name ) {
                        $selected = ( $filter_day === $day_name ) ? "selected='selected'" : "";
                        echo "<option value='" . esc_attr( $day_name ) . "' $selected>" . esc_html( ucfirst( $day_name ) ) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label>Periode (van/tot):</label>
                <!-- Min attributen verwijderd zodat filtering op verleden datums mogelijk is -->
                <input type="date" name="custom_from" value="<?php echo isset($_GET['custom_from']) ? esc_attr($_GET['custom_from']) : ''; ?>">
                <input type="date" name="custom_to" value="<?php echo isset($_GET['custom_to']) ? esc_attr($_GET['custom_to']) : ''; ?>">
            </div>
            <div>
                <label>Filter:</label>
                <select id="combined_filter" name="combined_filter">
                    <option value="">Alle events</option>
                    <option value="eigen" <?php selected($combined_filter, 'eigen'); ?>>Maatwerk</option>
                </select>
            </div>
            <div>
                <?php
                $toggle_url = add_query_arg( 'hide_cancelled', $show_cancelled ? '1' : false );
                $toggle_text = $show_cancelled ? 'Geannuleerde orders verbergen' : 'Geannuleerde orders tonen';
                echo "<a href='" . esc_url($toggle_url) . "' class='cancelled-toggle-button'><i class='fa fa-ban'></i> $toggle_text</a>";
                ?>
            </div>
        </div>
        <!-- Nieuwe layout: knoppen naast elkaar -->
        <div class="filter-buttons">
            <?php
                if ( is_admin() ) {
                    $reset_url = admin_url('admin.php?page=week-overzicht');
                } else {
                    $reset_url = remove_query_arg( array('weeks_ahead', 'day', 'order_search', 'custom_from', 'custom_to', 'combined_filter', 'hide_cancelled') );
                }
                echo "<a href='" . esc_url($reset_url) . "' class='reset-filters-button'><i class='fa fa-refresh'></i> Reset Filters</a>";
            ?>
            <button type="submit" class="details-button" style="background-color: #009640;"><i class="fa fa-search"></i> Start met filteren</button>
        </div>
    </form>
    <?php
    echo "</div>";
    
    // Alleen voor admin: Knoppen voor Partijen invoeren en Klant toevoegen
    echo '<div class="filter-bar" style="text-align: left; margin-bottom: 20px;">';
    if ( current_user_can('manage_options') ) {
        echo '<button onclick="openEventPopup()" class="details-button" style="background-color: #009640; color: #fff;"><i class="fa fa-plus"></i> Partij invoeren</button>';
        echo '<button onclick="openNewUserPopup()" class="details-button" style="background-color: #009640; color: #fff; margin-left:10px;"><i class="fa fa-user-plus"></i> Klant toevoegen</button>';
    }
    echo '<span style="display:inline-block; margin-left:10px;">' . do_shortcode('[custom_form_popup]') . '</span>';
    echo ' <span style="font-size:0.9em; color:#ccc; margin-left:10px;">Eigen partijen (maatwerk) invoeren in de agenda</span>';
    echo '</div>';

    ?>
    <div id="event-popup-overlay" class="popup-overlay">
        <div class="popup-content" id="event-popup-content">
             <span class="close-popup" onclick="closeEventPopup()">&times;</span>
             <h3>Partij invoeren</h3>
             <form method="post" id="custom-event-form" enctype="multipart/form-data">
                 <div class="form-section-title">Event Gegevens</div>
                 <div class="event-form-group full">
                     <div>
                         <label>Numer:</label>
                         <input type="text" name="custom_event_number" placeholder="Bijv. NA 12-1">
                     </div>
                 </div>
                 <div class="event-form-group">
                     <div>
                         <label>Status:</label>
                         <select name="custom_status" id="custom_status" title="Selecteer status (bijv. akkoord, in optie of geannuleerd)" onchange="toggleOptionDate(this.value, 'option_date_container_add')">
                             <option value="akkoord">Akkoord</option>
                             <option value="in optie">In optie</option>
                             <option value="geannuleerd">Geannuleerd</option>
                         </select>
                     </div>
                     <div id="option_date_container_add" style="display:none;">
                         <label>Optie Datum:</label>
                         <!-- min attribuut verwijderd -->
                         <input type="date" name="custom_option_date">
                     </div>
                 </div>
                 <div class="splitter"></div>
                 <?php $users = get_users(array('fields'=>array('ID','display_name'))); ?>
                 <div class="user-selector-container">
                     <label>Klant zoeken:</label>
                     <input type="text" id="user_input" list="user_datalist" placeholder="Zoek en selecteer gebruiker..." style="width:100%; padding:5px;">
                     <datalist id="user_datalist">
                         <option value="">-- Kies Gebruiker --</option>
                         <?php 
                         foreach ($users as $user) {
                             $company    = get_user_meta($user->ID, 'billing_company', true);
                             $first_name = get_user_meta($user->ID, 'first_name', true);
                             $last_name  = get_user_meta($user->ID, 'last_name', true);
                             $option_text = $company . " - " . $first_name . " " . $last_name;
                             echo "<option value='" . esc_attr($option_text) . "' data-userid='" . esc_attr($user->ID) . "' data-first='" . esc_attr($first_name) . "' data-last='" . esc_attr($last_name) . "' data-company='" . esc_attr($company) . "' data-address='" . esc_attr(get_user_meta($user->ID, 'billing_address_1', true)) . "' data-postcode='" . esc_attr(get_user_meta($user->ID, 'billing_postcode', true)) . "' data-city='" . esc_attr(get_user_meta($user->ID, 'billing_city', true)) . "' data-email='" . esc_attr(get_user_meta($user->ID, 'billing_email', true)) . "' data-phone='" . esc_attr(get_user_meta($user->ID, 'billing_phone', true)) . "' >";
                         }
                         echo "<option value='Hageman Catering - Bas Hageman' data-userid='example' data-first='Bas' data-last='Hageman' data-company='Hageman Catering' data-address='Voorbeeldstraat 1' data-postcode='1234 AB' data-city='Voorbeeldstad' data-email='bas@example.com' data-phone='0612345678'></option>";
                         ?>
                     </datalist>
                 </div>
                 <script>
                 document.getElementById('user_input').addEventListener('change', function(){
                     var inputValue = this.value;
                     var datalist = document.getElementById('user_datalist');
                     var options = datalist.options;
                     var selectedData = null;
                     for (var i = 0; i < options.length; i++) {
                         if (options[i].value === inputValue) {
                             selectedData = options[i];
                             break;
                         }
                     }
                     if (selectedData) {
                         document.querySelector('input[name="custom_first_name"]').value = selectedData.getAttribute('data-first') || "";
                         document.querySelector('input[name="custom_last_name"]').value = selectedData.getAttribute('data-last') || "";
                         document.querySelector('input[name="custom_company"]').value = selectedData.getAttribute('data-company') || "";
                         document.querySelector('input[name="custom_address"]').value = selectedData.getAttribute('data-address') || "";
                         document.querySelector('input[name="custom_postcode"]').value = selectedData.getAttribute('data-postcode') || "";
                         document.querySelector('input[name="custom_city"]').value = selectedData.getAttribute('data-city') || "";
                         document.querySelector('input[name="custom_email"]').value = selectedData.getAttribute('data-email') || "";
                         document.querySelector('input[name="custom_phone"]').value = selectedData.getAttribute('data-phone') || "";
                     }
                 });
                 </script>
                 <div class="event-form-group">
                     <div>
                         <label>Voornaam:</label>
                         <input type="text" name="custom_first_name" placeholder="Voornaam">
                     </div>
                     <div>
                         <label>Achternaam:</label>
                         <input type="text" name="custom_last_name" placeholder="Achternaam">
                     </div>
                 </div>
                 <div class="event-form-group">
                     <div>
                         <label>Email:</label>
                         <input type="email" name="custom_email" placeholder="Email">
                     </div>
                     <div>
                         <label>Telefoonnummer:</label>
                         <input type="text" name="custom_phone" placeholder="Telefoonnummer">
                     </div>
                 </div>
                 <div class="splitter"></div>
                 <div class="form-section-title">NAW Gegevens</div>
                 <div class="event-form-group full">
                     <div>
                         <label>Bedrijfsnaam:</label>
                         <input type="text" name="custom_company" placeholder="Bedrijfsnaam">
                     </div>
                 </div>
                 <div class="event-form-group full">
                     <div>
                         <label>Straat + Huisnummer:</label>
                         <input type="text" name="custom_address" placeholder="Straat + Huisnummer">
                     </div>
                 </div>
                 <div class="event-form-group">
                     <div>
                         <label>Postcode:</label>
                         <input type="text" name="custom_postcode" placeholder="Postcode">
                     </div>
                     <div>
                         <label>Plaats:</label>
                         <input type="text" name="custom_city" placeholder="Plaats">
                     </div>
                 </div>
                 <div class="splitter"></div>
                 <div class="form-section-title">Event Gegevens</div>
                 <div class="event-form-group full">
                     <div>
                         <label>Referentie:</label>
                         <input type="text" name="custom_reference" placeholder="Referentie">
                     </div>
                 </div>
                 <div class="event-form-group full">
                     <div>
                         <label>Datum:</label>
                         <!-- min attribuut verwijderd zodat verleden datums toegestaan zijn -->
                         <input type="date" name="custom_date" required>
                     </div>
                 </div>
                 <div class="event-form-group">
                     <div>
                         <label>Start tijd:</label>
                         <input type="time" name="custom_start_time">
                     </div>
                     <div>
                         <label>Eind tijd:</label>
                         <input type="time" name="custom_end_time">
                     </div>
                 </div>
                 <div class="event-form-group" style="gap: 10px;">
                     <div style="flex: 1;">
                         <label>Aantal medewerkers:</label>
                         <select name="custom_staff">
                             <option value="">geen personeel</option>
                             <?php for ($i = 1; $i <= 15; $i++): ?>
                                 <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                             <?php endfor; ?>
                         </select>
                     </div>
                     <div style="flex: 1;">
                         <label>Aantal Personen:</label>
                         <input type="number" name="custom_personen" placeholder="Bijv. 50">
                     </div>
                 </div>
                 <div class="splitter"></div>
                 <!-- PDF Upload Sectie -->
                 <div class="form-section-title">PDF Documenten</div>
                 <div id="pdf-upload-container">
                     <div class="pdf_upload_block">
                         <input type="file" name="custom_pdf_documents[]" accept="application/pdf">
                         <select name="custom_pdf_visibility[]">
                             <option value="public">Openbaar</option>
                             <option value="private">Privé</option>
                         </select>
                     </div>
                 </div>
                 <button type="button" class="add-pdf-button" onclick="addPdfUploadBlock('pdf-upload-container')">Voeg PDF toe</button><br>
                 <br>
                 <button type="submit" name="custom_event_submit" class="details-button" style="background-color: #009640;"><i class="fa fa-save"></i> Opslaan</button>
             </form>
        </div>
    </div>
    <?php if ( current_user_can('manage_options') ) : ?>
    <div id="new-user-popup" class="popup-overlay">
        <div class="popup-content">
            <span class="close-popup" onclick="closeNewUserPopup()">&times;</span>
            <h3>Nieuwe gebruiker toevoegen</h3>
            <form method="post" id="new-user-form">
                <div class="form-section-title">Gegevens</div>
                <div class="event-form-group full">
                    <div>
                        <label>Voornaam:</label>
                        <input type="text" name="new_first_name" value="<?php echo esc_attr( $new_user_data['first_name'] ); ?>">
                    </div>
                </div>
                <div class="event-form-group full">
                    <div>
                        <label>Achternaam:</label>
                        <input type="text" name="new_last_name" value="<?php echo esc_attr( $new_user_data['last_name'] ); ?>">
                    </div>
                </div>
                <div class="event-form-group full">
                    <div>
                        <label>Bedrijfsnaam (optioneel):</label>
                        <input type="text" name="new_company" value="<?php echo esc_attr( $new_user_data['company'] ); ?>">
                    </div>
                </div>
                <div class="event-form-group full">
                    <div>
                        <label>Straat (optioneel):</label>
                        <input type="text" name="new_street" value="<?php echo esc_attr( $new_user_data['street'] ); ?>">
                    </div>
                </div>
                <div class="event-form-group">
                    <div>
                        <label>Postcode (optioneel):</label>
                        <input type="text" name="new_postcode" value="<?php echo esc_attr( $new_user_data['postcode'] ); ?>">
                    </div>
                    <div>
                        <label>Plaats (optioneel):</label>
                        <input type="text" name="new_city" value="<?php echo esc_attr( $new_user_data['city'] ); ?>">
                    </div>
                </div>
                <div class="event-form-group full">
                    <div>
                        <label>Email (optioneel):</label>
                        <input type="email" name="new_email" value="<?php echo esc_attr( $new_user_data['email'] ); ?>">
                    </div>
                </div>
                <div class="event-form-group full">
                    <div>
                        <label>Telefoonnummer (optioneel):</label>
                        <input type="text" name="new_phone" value="<?php echo esc_attr( $new_user_data['phone'] ); ?>">
                    </div>
                </div>
                <br>
                <button type="submit" name="new_user_submit" class="details-button" style="background-color: #009640;"><i class="fa fa-user-plus"></i> Maak klant aan</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <div id="event-details-popup-overlay" class="popup-overlay">
        <div class="popup-content" id="event-details-popup-content"></div>
    </div>
    <div id="edit-event-popup-overlay" class="popup-overlay">
        <div class="popup-content" id="edit-event-popup-content"></div>
    </div>
    <script>
    if ( document.getElementById('user_input_edit_<?php echo isset($event['event_id']) ? esc_attr($event['event_id']) : ''; ?>') ) {
        document.getElementById('user_input_edit_<?php echo isset($event['event_id']) ? esc_attr($event['event_id']) : ''; ?>').addEventListener('change', function(){
            var inputValue = this.value;
            var datalist = document.getElementById('user_datalist_edit_<?php echo isset($event['event_id']) ? esc_attr($event['event_id']) : ''; ?>');
            var options = datalist.options;
            var selectedData = null;
            for (var i = 0; i < options.length; i++) {
                if (options[i].value === inputValue) {
                    selectedData = options[i];
                    break;
                }
            }
            if (selectedData) {
                document.querySelector('input[name="custom_first_name"]').value = selectedData.getAttribute('data-first') || "";
                document.querySelector('input[name="custom_last_name"]').value = selectedData.getAttribute('data-last') || "";
                document.querySelector('input[name="custom_company"]').value = selectedData.getAttribute('data-company') || "";
                document.querySelector('input[name="custom_address"]').value = selectedData.getAttribute('data-address') || "";
                document.querySelector('input[name="custom_postcode"]').value = selectedData.getAttribute('data-postcode') || "";
                document.querySelector('input[name="custom_city"]').value = selectedData.getAttribute('data-city') || "";
                document.querySelector('input[name="custom_email"]').value = selectedData.getAttribute('data-email') || "";
                document.querySelector('input[name="custom_phone"]').value = selectedData.getAttribute('data-phone') || "";
            }
        });
    }
    </script>
    <!-- Edit Event Popup -->
    <?php if ( current_user_can('manage_options') ) : ?>
    <div id="event-edit-<?php echo esc_attr($event['event_id']); ?>" style="display:none;">
        <h3>Event <?php echo esc_html($display_number); ?> Bewerken</h3>
        <form method="post" id="edit-event-form" enctype="multipart/form-data">
            <input type="hidden" name="custom_event_id" value="<?php echo esc_attr($event['event_id']); ?>">
            <div class="form-section-title">Event Gegevens</div>
            <div class="event-form-group full">
                <div>
                    <label>Event Nummer (Order Nummer):</label>
                    <input type="text" name="custom_event_number" value="<?php echo esc_attr($event['event_number']); ?>" placeholder="Bijv. 12345">
                </div>
            </div>
            <div class="event-form-group">
                <div>
                    <label>Status:</label>
                    <select name="custom_status" id="edit_custom_status_<?php echo esc_attr($event['event_id']); ?>" title="Selecteer status (bijv. akkoord, in optie of geannuleerd)" onchange="toggleOptionDate(this.value, 'option_date_container_edit_<?php echo esc_attr($event['event_id']); ?>')">
                        <option value="akkoord" <?php selected(strtolower($event['status']), 'akkoord'); ?>>Akkoord</option>
                        <option value="in optie" <?php selected(strtolower($event['status']), 'in optie'); ?>>In optie</option>
                        <option value="geannuleerd" <?php selected(strtolower($event['status']), 'geannuleerd'); ?>>Geannuleerd</option>
                    </select>
                </div>
                <div id="option_date_container_edit_<?php echo esc_attr($event['event_id']); ?>" style="display:<?php echo (strtolower($event['status']) === 'in optie' ? 'block' : 'none'); ?>;">
                    <label>Optie Datum:</label>
                    <!-- min attribuut verwijderd -->
                    <input type="date" name="custom_option_date" value="<?php echo esc_attr($event['option_date']); ?>">
                </div>
            </div>
            <div class="splitter"></div>
            <div class="form-section-title">Contact Gegevens</div>
            <div class="user-selector-container">
                <label>Selecteer gebruiker:</label>
                <input type="text" id="user_input_edit_<?php echo esc_attr($event['event_id']); ?>" list="user_datalist_edit_<?php echo esc_attr($event['event_id']); ?>" placeholder="Zoek en selecteer gebruiker..." style="width:100%; padding:5px;">
                <datalist id="user_datalist_edit_<?php echo esc_attr($event['event_id']); ?>">
                    <option value="">-- Kies Gebruiker --</option>
                    <?php 
                    foreach ($users as $user) {
                        $company    = get_user_meta($user->ID, 'billing_company', true);
                        $first_name = get_user_meta($user->ID, 'first_name', true);
                        $last_name  = get_user_meta($user->ID, 'last_name', true);
                        $option_text = $company . " - " . $first_name . " " . $last_name;
                        echo "<option value='" . esc_attr($option_text) . "' data-userid='" . esc_attr($user->ID) . "' data-first='" . esc_attr($first_name) . "' data-last='" . esc_attr($last_name) . "' data-company='" . esc_attr($company) . "' data-address='" . esc_attr(get_user_meta($user->ID, 'billing_address_1', true)) . "' data-postcode='" . esc_attr(get_user_meta($user->ID, 'billing_postcode', true)) . "' data-city='" . esc_attr(get_user_meta($user->ID, 'billing_city', true)) . "' data-email='" . esc_attr(get_user_meta($user->ID, 'billing_email', true)) . "' data-phone='" . esc_attr(get_user_meta($user->ID, 'billing_phone', true)) . "' >";
                    }
                    echo "<option value='Hageman Catering - Bas Hageman' data-userid='example' data-first='Bas' data-last='Hageman' data-company='Hageman Catering' data-address='Voorbeeldstraat 1' data-postcode='1234 AB' data-city='Voorbeeldstad' data-email='bas@example.com' data-phone='0612345678'></option>";
                    ?>
                </datalist>
                <button type="button" class="btn-add-user" onclick="openNewUserPopup()">Nieuwe gebruiker toevoegen</button>
            </div>
            <div class="event-form-group">
                <div>
                    <label>Voornaam:</label>
                    <input type="text" name="custom_first_name" value="<?php echo esc_attr($event['first_name']); ?>" placeholder="Voornaam">
                </div>
                <div>
                    <label>Achternaam:</label>
                    <input type="text" name="custom_last_name" value="<?php echo esc_attr($event['last_name']); ?>" placeholder="Achternaam">
                </div>
            </div>
            <div class="event-form-group">
                <div>
                    <label>Email:</label>
                    <input type="email" name="custom_email" value="<?php echo esc_attr($event['email']); ?>" placeholder="Email">
                </div>
                <div>
                    <label>Telefoonnummer:</label>
                    <input type="text" name="custom_phone" value="<?php echo esc_attr($event['phone']); ?>" placeholder="Telefoonnummer">
                </div>
            </div>
            <div class="form-section-title">NAW Gegevens</div>
            <div class="event-form-group full">
                <div>
                    <label>Bedrijfsnaam:</label>
                    <input type="text" name="custom_company" value="<?php echo esc_attr($event['company']); ?>" placeholder="Bedrijfsnaam">
                </div>
            </div>
            <div class="event-form-group full">
                <div>
                    <label>Straat + Huisnummer:</label>
                    <input type="text" name="custom_address" value="<?php echo esc_attr($event['address']); ?>" placeholder="Straat + Huisnummer">
                </div>
            </div>
            <div class="event-form-group">
                <div>
                    <label>Postcode:</label>
                    <input type="text" name="custom_postcode" value="<?php echo esc_attr($event['postcode']); ?>" placeholder="Postcode">
                </div>
                <div>
                    <label>Plaats:</label>
                    <input type="text" name="custom_city" value="<?php echo esc_attr($event['city']); ?>" placeholder="Plaats">
                </div>
            </div>
            <div class="splitter"></div>
            <div class="form-section-title">Event Gegevens</div>
            <div class="event-form-group full">
                <div>
                    <label>Referentie:</label>
                    <input type="text" name="custom_reference" value="<?php echo esc_attr($event['reference']); ?>" placeholder="Referentie">
                </div>
            </div>
            <div class="event-form-group full">
                <div>
                    <label>Datum:</label>
                    <!-- min attribuut verwijderd -->
                    <input type="date" name="custom_date" value="<?php echo esc_attr($event['date']); ?>" required>
                </div>
            </div>
            <div class="event-form-group">
                <div>
                    <label>Start tijd:</label>
                    <input type="time" name="custom_start_time" value="<?php echo esc_attr($event['start_time']); ?>">
                </div>
                <div>
                    <label>Eind tijd:</label>
                    <input type="time" name="custom_end_time" value="<?php echo esc_attr($event['end_time']); ?>">
                </div>
            </div>
            <div class="event-form-group" style="gap: 10px;">
                <div style="flex: 1;">
                    <label>Aantal medewerkers:</label>
                    <select name="custom_staff">
                        <option value="">geen personeel</option>
                        <?php for ($i = 1; $i <= 15; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected($event['staff'], $i); ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Aantal Personen:</label>
                    <input type="number" name="custom_personen" value="<?php echo esc_attr($event['personen']); ?>" placeholder="Bijv. 50">
                </div>
            </div>
            <div class="splitter"></div>
            <div class="event-form-group full">
                <div>
                    <label>Notitie:</label>
                    <textarea name="custom_note" placeholder="Er is..."><?php echo esc_textarea($event['note']); ?></textarea>
                </div>
            </div>
            <div class="event-form-group full">
                <div>
                    <label>Party Rental Besteld:</label>
                    <select name="custom_party_rental_besteld">
                        <option value="">-- Selecteer --</option>
                        <option value="ja" <?php selected($event['custom_party_rental_besteld'], 'ja'); ?>>Ja</option>
                        <option value="nee" <?php selected($event['custom_party_rental_besteld'], 'nee'); ?>>Nee</option>
                        <option value="niet nodig" <?php selected($event['custom_party_rental_besteld'], 'niet nodig'); ?>>Niet nodig</option>
                    </select>
                </div>
            </div>
            <!-- PDF Upload Sectie in Edit Form -->
            <div class="form-section-title">PDF Documenten</div>
            <div id="pdf-upload-container-edit-<?php echo esc_attr($event['event_id']); ?>">
                <?php 
                if( !empty($event['pdf_documents']) ){
                    foreach($event['pdf_documents'] as $index => $pdf){
                        echo "<div class='pdf_upload_block' style='margin-bottom:10px;'>";
                        if(is_array($pdf)){
                            echo "<a href='".esc_url($pdf['url'])."' target='_blank'>".esc_html(basename($pdf['url'])) ."</a> (" . esc_html($pdf['upload_date']) . ") ";
                            // Alleen beheerder mag verwijderen
                            if ( current_user_can('manage_options') ) {
                                echo "<a class='delete-button' href='" . esc_url( add_query_arg(array('delete_pdf_event'=>$event['event_id'], 'delete_pdf_index'=>$index)) ) . "' onclick='return confirm(\"Weet je zeker dat je dit PDF wilt verwijderen?\");'><i class='fa fa-trash'></i> Verwijder PDF</a>";
                            }
                        } else {
                            echo "<a href='".esc_url($pdf)."' target='_blank'>".esc_html(basename($pdf))."</a> ";
                            if ( current_user_can('manage_options') ) {
                                echo "<a class='delete-button' href='" . esc_url( add_query_arg(array('delete_pdf_event'=>$event['event_id'], 'delete_pdf_index'=>$index)) ) . "' onclick='return confirm(\"Weet je zeker dat je dit PDF wilt verwijderen?\");'><i class='fa fa-trash'></i> Verwijder PDF</a>";
                            }
                        }
                        echo "</div>";
                    }
                }
                ?>
            </div>
            <button type="button" class="add-pdf-button" onclick="addPdfUploadBlock('pdf-upload-container-edit-<?php echo esc_attr($event['event_id']); ?>')">Voeg PDF toe</button>
            <br>
            <button type="submit" name="custom_event_update" class="details-button" style="background-color: #009640;"><i class="fa fa-save"></i> Opslaan</button>
        </form>
        <script>
        document.getElementById('user_input_edit_<?php echo esc_attr($event['event_id']); ?>').addEventListener('change', function(){
            var inputValue = this.value;
            var datalist = document.getElementById('user_datalist_edit_<?php echo esc_attr($event['event_id']); ?>');
            var options = datalist.options;
            var selectedData = null;
            for (var i = 0; i < options.length; i++) {
                if (options[i].value === inputValue) {
                    selectedData = options[i];
                    break;
                }
            }
            if (selectedData) {
                document.querySelector('input[name="custom_first_name"]').value = selectedData.getAttribute('data-first') || "";
                document.querySelector('input[name="custom_last_name"]').value = selectedData.getAttribute('data-last') || "";
                document.querySelector('input[name="custom_company"]').value = selectedData.getAttribute('data-company') || "";
                document.querySelector('input[name="custom_address"]').value = selectedData.getAttribute('data-address') || "";
                document.querySelector('input[name="custom_postcode"]').value = selectedData.getAttribute('data-postcode') || "";
                document.querySelector('input[name="custom_city"]').value = selectedData.getAttribute('data-city') || "";
                document.querySelector('input[name="custom_email"]').value = selectedData.getAttribute('data-email') || "";
                document.querySelector('input[name="custom_phone"]').value = selectedData.getAttribute('data-phone') || "";
            }
        });
        </script>
    </div>
    <?php endif; ?>

    <?php
    echo "<div class='orders-container'>";
    if ( empty($combined) ) {
        echo "<br>Er zijn geen items gevonden.<br><br><br>";
    }
    foreach ( $combined as $item ) {
        if ( $item['type'] === 'order' ) {
            $order = $item['data'];
            $order_id = $order->get_id();
            $is_cancelled = ( $order->get_status() === 'cancelled' );
            $billing_company = $order->get_billing_company();
            $delivery_date = get_post_meta( $order_id, 'pi_system_delivery_date', true );
            $delivery_date_formatted = $delivery_date ? date_i18n( 'l j F Y', strtotime($delivery_date) ) : '';
            $delivery_time = get_post_meta( $order_id, 'pi_delivery_time', true );
            $order_end_time = get_post_meta( $order_id, 'order_eindtijd', true );
            $order_reference = get_post_meta( $order_id, 'order_reference', true );
            $order_personen = get_post_meta( $order_id, 'order_personen', true );
            $customer_note = $order->get_customer_note();
            $order_number = $order->get_order_number();
            $display_number = ( isset($rename_numbers[$order_number]) ) ? $rename_numbers[$order_number] : "#$order_number";
            ?>
            <div class="order-card <?php echo $is_cancelled ? 'cancelled-order' : ''; ?>">
                <div class="order-card-header">
                    <?php 
                        if($is_cancelled){
                            echo "<span class='order-badge cancelled'>$display_number</span>";
                        } else {
                            echo "<span class='order-badge default'>$display_number</span>";
                        }
                    ?>
                </div>
                <div class="order-card-body">
                    <div><strong>Bedrijfsnaam:</strong> <?php echo esc_html($billing_company); ?></div>
                    <div><strong>Datum:</strong> <?php echo esc_html($delivery_date_formatted); ?></div>
                    <div><strong>Tijd:</strong> <?php echo esc_html($delivery_time . " - " . $order_end_time); ?></div>
                    <div><strong>Zaal:</strong> <?php echo esc_html( get_post_meta($order_id, 'order_location', true) ); ?></div>
                    <div><strong>Referentie:</strong> <?php echo esc_html($order_reference); ?></div>
                    <div><strong>Party Rental Besteld:</strong> <?php echo esc_html( get_post_meta($order_id, 'party_rental_besteld', true) ); ?></div>
                    <div><strong>Aantal Personen:</strong> <?php echo esc_html($order_personen); ?></div>
                    <div><strong>Notitie:</strong> <?php echo esc_html($customer_note); ?></div>
                    <div class="action-buttons">
                        <span class="details-button" onclick="openPopup('<?php echo esc_js($order_number); ?>')"><i class="fa fa-eye"></i> Details</span>
                        <span class="pdf-button" onclick="window.open('<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&document_type=packing-slip&order_ids=' . $order_id ), 'generate_wpo_wcpdf' ) ); ?>','_blank')"><i class="fa fa-file-pdf-o"></i> PDF</span>
                    </div>
                </div>
            </div>
            <div id="details-<?php echo esc_attr($order_number); ?>" class="order-details">
                <h3>Order Details: <?php echo esc_html($display_number); ?> - <?php echo esc_html($delivery_date_formatted); ?></h3>
                <?php
                echo "<div><strong>Adres:</strong> " . esc_html($order->get_billing_address_1()) . "</div>";
                echo "<div><strong>Contactpersoon:</strong> " . esc_html($order->get_billing_first_name() . " " . $order->get_billing_last_name()) . "</div>";
                echo "<div><strong>Contact:</strong> " . esc_html($order->get_billing_email() . " / " . $order->get_billing_phone()) . "</div>";
                echo "<div><strong>Zaal:</strong> " . esc_html( get_post_meta($order_id, 'order_location', true) ) . "</div>";
                echo "<div><strong>Referentie:</strong> " . esc_html($order_reference) . "</div>";
                echo "<div><strong>Party Rental Besteld:</strong> " . esc_html( get_post_meta($order_id, 'party_rental_besteld', true) ) . "</div>";
                echo "<div><strong>Tijd:</strong> " . esc_html($delivery_time . " - " . $order_end_time) . "</div>";
                echo "<div><strong>Aantal Personen:</strong> " . esc_html($order_personen) . "</div>";
                echo "<div><strong>Notitie:</strong> " . esc_html($customer_note) . "</div>";
                ?>
            </div>
            <?php
        } else {
            $event = $item['data'];
            $formatted_date = date_i18n( 'l j F Y', strtotime($event['date']) );
            $display_number = ! empty($event['event_number']) ? $event['event_number'] : $event['event_id'];
            if ( isset($rename_numbers[$display_number]) ) {
                $display_number = $rename_numbers[$display_number];
            } else {
                $display_number = "#$display_number";
            }
            if ( isset($event['status']) && strtolower($event['status']) === 'akkoord' ) {
                $badge_class = 'order-badge event event-akkoord';
                $option_badge = '';
            } elseif ( isset($event['status']) && strtolower($event['status']) === 'in optie' ) {
                $badge_class = 'order-badge event event-in optie';
                if ( ! empty($event['option_date']) ) {
                    $today = new DateTime(date('Y-m-d'));
                    $optionDate = new DateTime($event['option_date']);
                    $diff = $today->diff($optionDate);
                    if ( $diff->days == 0 ) {
                        $countdown = "verloopt vandaag";
                    } elseif ( ! $diff->invert ) {
                        $countdown = "nog " . $diff->days . " dagen in optie";
                    } else {
                        $countdown = $diff->days . " dagen verlopen";
                    }
                    $class = 'option-badge';
                    if ( $diff->invert ) {
                        $class .= ' expired';
                    }
                    $option_badge = '<span class="' . $class . '">' . $countdown . '</span>';
                } else {
                    $option_badge = '';
                }
            } elseif ( isset($event['status']) && strtolower($event['status']) === 'geannuleerd' ) {
                $badge_class = 'order-badge event event-geannuleerd';
                $option_badge = '';
            } else {
                $badge_class = 'order-badge event';
                $option_badge = '';
            }
            ?>
            <div class="order-card" style="background-color: #2E2E3A;">
                <div class="event-card-header">
                    <?php 
                        echo "<span class='$badge_class'>$display_number</span> " . $option_badge;
                    ?>
                </div>
                <div class="order-card-body">
                    <div><strong><?php echo esc_html($event['company']); ?></strong><br>
                    <?php echo esc_html($event['reference']); ?><br>
                    <?php echo esc_html($formatted_date); ?><br>
                    <?php echo esc_html($event['start_time'] . " - " . $event['end_time']); ?><br></div>
                    <div><strong>Aantal Personen:</strong> <?php echo esc_html($event['personen']); ?><br>
                    <strong>Aantal Medewerkers:</strong> <?php echo esc_html($event['staff']); ?></div>
                    <div><strong>Notitie:</strong> <?php echo esc_html($event['note']); ?></div>
                    <div><strong>Party Rental Besteld:</strong> <?php echo esc_html($event['custom_party_rental_besteld']); ?></div>
                    <?php
                    if ( isset($event['custom_client_tag']) && $event['custom_client_tag'] && $combined_filter !== 'eigen' ) {
                        echo "<div><strong>Product Tag:</strong> " . esc_html($event['custom_client_tag']) . "</div>";
                    }
                    ?>
                    <div class="action-buttons">
                        <span class="details-button" onclick="openPopupEvent('<?php echo esc_js($event['event_id']); ?>')"><i class="fa fa-eye"></i> Details</span>
                        <?php if ( current_user_can('manage_options') ) : ?>
                        <span class="edit-button" onclick="openEditEvent('<?php echo esc_js($event['event_id']); ?>')"><i class="fa fa-edit"></i> Bewerk</span>
                        <a class="delete-button" href="<?php echo esc_url( add_query_arg('delete_event', $event['event_id']) ); ?>" onclick="return confirm('Weet je zeker dat je dit event wilt verwijderen?');"><i class="fa fa-trash"></i> Verwijder</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div id="event-details-<?php echo esc_attr($event['event_id']); ?>" class="event-details">
                <h3><?php echo esc_html($display_number); ?> - <?php echo esc_html($formatted_date); ?></h3>
                <div><strong>Adres:</strong> <?php echo esc_html($event['address']); ?>, <?php echo esc_html($event['postcode'] . ", " . $event['city']); ?></div><br>
                <div><strong>Contactpersoon:</strong> <?php echo esc_html($event['first_name'] . " " . $event['last_name']); ?></div>
                <div><strong>Contact:</strong> <?php echo esc_html($event['email'] . " / " . $event['phone']); ?></div><br>
                <div><strong>Referentie:</strong> <?php echo esc_html($event['reference']); ?></div>
                <div><strong>Party Rental Besteld:</strong> <?php echo esc_html($event['custom_party_rental_besteld']); ?></div>
                <div><strong>Tijd:</strong> <?php echo esc_html($event['start_time'] . " - " . $event['end_time']); ?></div><br>
                <div><strong>Aantal Personen:</strong> <?php echo esc_html($event['personen']); ?></div>
                <div><strong>Aantal Medewerkers:</strong> <?php echo esc_html($event['staff']); ?></div><br>
                <div><strong>Notitie:</strong><br><?php echo esc_html($event['note']); ?></div>
                <?php
                if ( isset($event['custom_client_tag']) && $event['custom_client_tag'] && $combined_filter !== 'eigen' ) {
                    echo "<div><strong>Product Tag:</strong> " . esc_html($event['custom_client_tag']) . "</div>";
                }
                if ( ! empty($event['pdf_documents']) ) {
                    echo "<div><strong>PDF Documenten:</strong><br>";
                    foreach($event['pdf_documents'] as $index => $pdf){
                        if ( is_array($pdf) ) {
                            // Alleen als public of beheerder
                            if ($pdf['visibility'] === 'private' && ! current_user_can('manage_options')) {
                                continue;
                            }
                            echo "<a href='".esc_url($pdf['url'])."' target='_blank'>".esc_html(basename($pdf['url'])) ."</a> (" . esc_html($pdf['upload_date']) . ") ";
                            if ( current_user_can('manage_options') ) {
                                echo "<a class='delete-button' href='" . esc_url( add_query_arg(array('delete_pdf_event'=>$event['event_id'], 'delete_pdf_index'=>$index)) ) . "' onclick='return confirm(\"Weet je zeker dat je dit PDF wilt verwijderen?\");'><i class='fa fa-trash'></i> Verwijder PDF</a><br>";
                            }
                        } else {
                            echo "<a href='".esc_url($pdf)."' target='_blank'>".esc_html(basename($pdf))."</a><br>";
                        }
                    }
                    echo "</div>";
                }
                if ( isset($event['created_by']) && isset($event['last_modified_by']) ) {
                    $creator = get_userdata($event['created_by']);
                    $modifier = get_userdata($event['last_modified_by']);
                    echo "<div class='logboek'>";
                    echo "<div>Aangemaakt door: " . ($creator ? esc_html($creator->display_name) : 'Onbekend') . " op " . (isset($event['created_at']) ? esc_html($event['created_at']) : '') . "</div>";
                    echo "<div>Bewerkt door: " . ($modifier ? esc_html($modifier->display_name) : 'Onbekend') . " op " . (isset($event['last_modified_at']) ? esc_html($event['last_modified_at']) : '') . "</div>";
                    echo "</div>";
                }
                ?>
            </div>
            <div id="event-edit-<?php echo esc_attr($event['event_id']); ?>" style="display:none;">
                <h3>Event <?php echo esc_html($display_number); ?> Bewerken</h3>
                <form method="post" id="edit-event-form" enctype="multipart/form-data">
                    <input type="hidden" name="custom_event_id" value="<?php echo esc_attr($event['event_id']); ?>">
                    <div class="form-section-title">Event Gegevens</div>
                    <div class="event-form-group full">
                        <div>
                            <label>Event Nummer (Order Nummer):</label>
                            <input type="text" name="custom_event_number" value="<?php echo esc_attr($event['event_number']); ?>" placeholder="Bijv. 12345">
                        </div>
                    </div>
                    <div class="event-form-group">
                        <div>
                            <label>Status:</label>
                            <select name="custom_status" id="edit_custom_status_<?php echo esc_attr($event['event_id']); ?>" title="Selecteer status (bijv. akkoord, in optie of geannuleerd)" onchange="toggleOptionDate(this.value, 'option_date_container_edit_<?php echo esc_attr($event['event_id']); ?>')">
                                <option value="akkoord" <?php selected(strtolower($event['status']), 'akkoord'); ?>>Akkoord</option>
                                <option value="in optie" <?php selected(strtolower($event['status']), 'in optie'); ?>>In optie</option>
                                <option value="geannuleerd" <?php selected(strtolower($event['status']), 'geannuleerd'); ?>>Geannuleerd</option>
                            </select>
                        </div>
                        <div id="option_date_container_edit_<?php echo esc_attr($event['event_id']); ?>" style="display:<?php echo (strtolower($event['status']) === 'in optie' ? 'block' : 'none'); ?>;">
                            <label>Optie Datum:</label>
                            <!-- min attribuut verwijderd -->
                            <input type="date" name="custom_option_date" value="<?php echo esc_attr($event['option_date']); ?>">
                        </div>
                    </div>
                    <div class="splitter"></div>
                    <div class="form-section-title">Contact Gegevens</div>
                    <div class="user-selector-container">
                        <label>Selecteer gebruiker:</label>
                        <input type="text" id="user_input_edit_<?php echo esc_attr($event['event_id']); ?>" list="user_datalist_edit_<?php echo esc_attr($event['event_id']); ?>" placeholder="Zoek en selecteer gebruiker..." style="width:100%; padding:5px;">
                        <datalist id="user_datalist_edit_<?php echo esc_attr($event['event_id']); ?>">
                            <option value="">-- Kies Gebruiker --</option>
                            <?php 
                            foreach ($users as $user) {
                                $company    = get_user_meta($user->ID, 'billing_company', true);
                                $first_name = get_user_meta($user->ID, 'first_name', true);
                                $last_name  = get_user_meta($user->ID, 'last_name', true);
                                $option_text = $company . " - " . $first_name . " " . $last_name;
                                echo "<option value='" . esc_attr($option_text) . "' data-userid='" . esc_attr($user->ID) . "' data-first='" . esc_attr($first_name) . "' data-last='" . esc_attr($last_name) . "' data-company='" . esc_attr($company) . "' data-address='" . esc_attr(get_user_meta($user->ID, 'billing_address_1', true)) . "' data-postcode='" . esc_attr(get_user_meta($user->ID, 'billing_postcode', true)) . "' data-city='" . esc_attr(get_user_meta($user->ID, 'billing_city', true)) . "' data-email='" . esc_attr(get_user_meta($user->ID, 'billing_email', true)) . "' data-phone='" . esc_attr(get_user_meta($user->ID, 'billing_phone', true)) . "' >";
                            }
                            echo "<option value='Hageman Catering - Bas Hageman' data-userid='example' data-first='Bas' data-last='Hageman' data-company='Hageman Catering' data-address='Voorbeeldstraat 1' data-postcode='1234 AB' data-city='Voorbeeldstad' data-email='bas@example.com' data-phone='0612345678'></option>";
                            ?>
                        </datalist>
                        <button type="button" class="btn-add-user" onclick="openNewUserPopup()">Nieuwe klant toevoegen</button>
                    </div>
                    <div class="event-form-group">
                        <div>
                            <label>Voornaam:</label>
                            <input type="text" name="custom_first_name" value="<?php echo esc_attr($event['first_name']); ?>" placeholder="Voornaam">
                        </div>
                        <div>
                            <label>Achternaam:</label>
                            <input type="text" name="custom_last_name" value="<?php echo esc_attr($event['last_name']); ?>" placeholder="Achternaam">
                        </div>
                    </div>
                    <div class="event-form-group">
                        <div>
                            <label>Email:</label>
                            <input type="email" name="custom_email" value="<?php echo esc_attr($event['email']); ?>" placeholder="Email">
                        </div>
                        <div>
                            <label>Telefoonnummer:</label>
                            <input type="text" name="custom_phone" value="<?php echo esc_attr($event['phone']); ?>" placeholder="Telefoonnummer">
                        </div>
                    </div>
                    <div class="form-section-title">NAW Gegevens</div>
                    <div class="event-form-group full">
                        <div>
                            <label>Bedrijfsnaam:</label>
                            <input type="text" name="custom_company" value="<?php echo esc_attr($event['company']); ?>" placeholder="Bedrijfsnaam">
                        </div>
                    </div>
                    <div class="event-form-group full">
                        <div>
                            <label>Straat + Huisnummer:</label>
                            <input type="text" name="custom_address" value="<?php echo esc_attr($event['address']); ?>" placeholder="Straat + Huisnummer">
                        </div>
                    </div>
                    <div class="event-form-group">
                        <div>
                            <label>Postcode:</label>
                            <input type="text" name="custom_postcode" value="<?php echo esc_attr($event['postcode']); ?>" placeholder="Postcode">
                        </div>
                        <div>
                            <label>Plaats:</label>
                            <input type="text" name="custom_city" value="<?php echo esc_attr($event['city']); ?>" placeholder="Plaats">
                        </div>
                    </div>
                    <div class="splitter"></div>
                    <div class="form-section-title">Event Gegevens</div>
                    <div class="event-form-group full">
                        <div>
                            <label>Referentie:</label>
                            <input type="text" name="custom_reference" value="<?php echo esc_attr($event['reference']); ?>" placeholder="Referentie">
                        </div>
                    </div>
                    <div class="event-form-group full">
                        <div>
                            <label>Datum:</label>
                            <!-- min attribuut verwijderd -->
                            <input type="date" name="custom_date" value="<?php echo esc_attr($event['date']); ?>" required>
                        </div>
                    </div>
                    <div class="event-form-group">
                        <div>
                            <label>Start tijd:</label>
                            <input type="time" name="custom_start_time" value="<?php echo esc_attr($event['start_time']); ?>">
                        </div>
                        <div>
                            <label>Eind tijd:</label>
                            <input type="time" name="custom_end_time" value="<?php echo esc_attr($event['end_time']); ?>">
                        </div>
                    </div>
                    <div class="event-form-group" style="gap: 10px;">
                        <div style="flex: 1;">
                            <label>Aantal medewerkers:</label>
                            <select name="custom_staff">
                                <option value="">geen personeel</option>
                                <?php for ($i = 1; $i <= 15; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($event['staff'], $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label>Aantal Personen:</label>
                            <input type="number" name="custom_personen" value="<?php echo esc_attr($event['personen']); ?>" placeholder="Bijv. 50">
                        </div>
                    </div>
                    <div class="splitter"></div>
                    <div class="event-form-group full">
                        <div>
                            <label>Notitie:</label>
                            <textarea name="custom_note" placeholder="Er is..."><?php echo esc_textarea($event['note']); ?></textarea>
                        </div>
                    </div>
                    <div class="event-form-group full">
                        <div>
                            <label>Party Rental Besteld:</label>
                            <select name="custom_party_rental_besteld">
                                <option value="">-- Selecteer --</option>
                                <option value="ja" <?php selected($event['custom_party_rental_besteld'], 'ja'); ?>>Ja</option>
                                <option value="nee" <?php selected($event['custom_party_rental_besteld'], 'nee'); ?>>Nee</option>
                                <option value="niet nodig" <?php selected($event['custom_party_rental_besteld'], 'niet nodig'); ?>>Niet nodig</option>
                            </select>
                        </div>
                    </div>
                    <!-- PDF Upload Sectie in Edit Form -->
                    <div class="form-section-title">PDF Documenten</div>
                    <div id="pdf-upload-container-edit-<?php echo esc_attr($event['event_id']); ?>">
                        <?php 
                        if( !empty($event['pdf_documents']) ){
                            foreach($event['pdf_documents'] as $index => $pdf){
                                echo "<div class='pdf_upload_block' style='margin-bottom:10px;'>";
                                if(is_array($pdf)){
                                    echo "<a href='".esc_url($pdf['url'])."' target='_blank'>".esc_html(basename($pdf['url'])) ."</a> (" . esc_html($pdf['upload_date']) . ") ";
                                    echo "<a class='delete-button' href='" . esc_url( add_query_arg(array('delete_pdf_event'=>$event['event_id'], 'delete_pdf_index'=>$index)) ) . "' onclick='return confirm(\"Weet je zeker dat je dit PDF wilt verwijderen?\");'><i class='fa fa-trash'></i> Verwijder PDF</a>";
                                } else {
                                    echo "<a href='".esc_url($pdf)."' target='_blank'>".esc_html(basename($pdf))."</a> ";
                                    echo "<a class='delete-button' href='" . esc_url( add_query_arg(array('delete_pdf_event'=>$event['event_id'], 'delete_pdf_index'=>$index)) ) . "' onclick='return confirm(\"Weet je zeker dat je dit PDF wilt verwijderen?\");'><i class='fa fa-trash'></i> Verwijder PDF</a>";
                                }
                                echo "</div>";
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="add-pdf-button" onclick="addPdfUploadBlock('pdf-upload-container-edit-<?php echo esc_attr($event['event_id']); ?>')">Voeg PDF toe</button>
                    <br>
                    <button type="submit" name="custom_event_update" class="details-button" style="background-color: #009640;"><i class="fa fa-save"></i> Opslaan</button>
                </form>
                <script>
                document.getElementById('user_input_edit_<?php echo esc_attr($event['event_id']); ?>').addEventListener('change', function(){
                    var inputValue = this.value;
                    var datalist = document.getElementById('user_datalist_edit_<?php echo esc_attr($event['event_id']); ?>');
                    var options = datalist.options;
                    var selectedData = null;
                    for (var i = 0; i < options.length; i++) {
                        if (options[i].value === inputValue) {
                            selectedData = options[i];
                            break;
                        }
                    }
                    if (selectedData) {
                        document.querySelector('input[name="custom_first_name"]').value = selectedData.getAttribute('data-first') || "";
                        document.querySelector('input[name="custom_last_name"]').value = selectedData.getAttribute('data-last') || "";
                        document.querySelector('input[name="custom_company"]').value = selectedData.getAttribute('data-company') || "";
                        document.querySelector('input[name="custom_address"]').value = selectedData.getAttribute('data-address') || "";
                        document.querySelector('input[name="custom_postcode"]').value = selectedData.getAttribute('data-postcode') || "";
                        document.querySelector('input[name="custom_city"]').value = selectedData.getAttribute('data-city') || "";
                        document.querySelector('input[name="custom_email"]').value = selectedData.getAttribute('data-email') || "";
                        document.querySelector('input[name="custom_phone"]').value = selectedData.getAttribute('data-phone') || "";
                    }
                });
                </script>
            </div>
            <?php
        }
    }
    echo "</div>";
    
    // Navigatieknoppen (alleen tonen indien geen custom periode is ingesteld)
    if ( ! ( isset($_GET['custom_from']) && !empty($_GET['custom_from']) && isset($_GET['custom_to']) && !empty($_GET['custom_to']) ) ) {
        echo "<div class='navigation-buttons'>";
        if ($weeks_ahead > 0) {
            $prev_week_url = add_query_arg( 'weeks_ahead', $weeks_ahead - 1 );
            $prev_week_url = add_query_arg( array(
                'day'             => $filter_day ?: false,
                'order_search'    => $filter_order ?: false,
                'custom_from'     => isset($_GET['custom_from']) ? $_GET['custom_from'] : false,
                'custom_to'       => isset($_GET['custom_to']) ? $_GET['custom_to'] : false,
                'combined_filter' => $combined_filter ?: false,
            ), $prev_week_url );
            echo "<a href='" . esc_url($prev_week_url) . "' class='navigation-button'>Vorige periode</a> ";
        }
        $next_week_url = add_query_arg( 'weeks_ahead', $weeks_ahead + 1 );
        $next_week_url = add_query_arg( array(
            'day'             => $filter_day ?: false,
            'order_search'    => $filter_order ?: false,
            'custom_from'     => isset($_GET['custom_from']) ? $_GET['custom_from'] : false,
            'custom_to'       => isset($_GET['custom_to']) ? $_GET['custom_to'] : false,
            'combined_filter' => $combined_filter ?: false,
        ), $next_week_url );
        echo "<a href='" . esc_url( $next_week_url ) . "' class='navigation-button'>Volgende periode</a>";
        echo "</div>";
    }
    
    $output = ob_get_clean();
    set_transient( $cache_key, $output, 300 );
    return $output;
}

/**
 * Frontend Shortcode
 */
function display_orders_current_week_lite_frontend( $atts ) {
    return get_orders_current_week_output( true );
}
add_shortcode( 'orders_current_week_lite', 'display_orders_current_week_lite_frontend' );

/**
 * Backend functie
 */
function display_orders_current_week_lite_backend() {
    echo get_orders_current_week_output();
}

/**
 * Voeg backend pagina toe aan het adminmenu: "Orders Overzicht"
 */
add_action( 'admin_menu', 'register_week_overzicht_admin_page' );
function register_week_overzicht_admin_page() {
    add_menu_page(
        'Orders Overzicht',
        'Orders Overzicht',
        'manage_options',
        'week-overzicht',
        'render_week_overzicht_admin_page',
        'dashicons-calendar-alt',
        3
    );
}

/**
 * Callback voor de backendpagina "Orders Overzicht"
 */
function render_week_overzicht_admin_page() {
    echo '<div class="wrap">';
    display_orders_current_week_lite_backend();
    echo '</div>';
}
