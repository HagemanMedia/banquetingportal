
error_log('DEBUG: File loaded.'); // Debug log: Controleert of het bestand wordt geladen

// Voeg deze code toe aan functions.php of in een plugin

add_shortcode('wc_orders_week_agenda', 'show_wc_orders_week_agenda');

/**
 * Genereert de wekelijkse agenda van WooCommerce bestellingen en maatwerk bestellingen.
 *
 * @param array $atts Shortcode attributen. Accepteert 'start_date' (YYYY/MM/DD).
 * @return string De HTML output van de agenda.
 */
function show_wc_orders_week_agenda($atts) {
    error_log('show_wc_orders_week_agenda function called.'); // Debug log

    // --- Toegangscontrole Logica ---
    // Haal de ID van de huidige post/pagina op waar de shortcode wordt gebruikt
    $current_post_id = get_the_ID();
    // Haal de zichtbaarheidsinstelling voor de agenda op uit de post meta.
    // De verwachte waarden zijn 'interne_medewerkers' of 'alle_werknemers'.
    $agenda_visibility_setting = get_post_meta($current_post_id, '_agenda_visibility_setting', true);

    // Standaard zichtbaarheid als deze niet is ingesteld op de pagina
    if (empty($agenda_visibility_setting)) {
        $agenda_visibility_setting = 'alle_werknemers'; // Standaard instellen op "Alle Werknemers"
    }

    $current_user = wp_get_current_user();
    $user_roles = (array) $current_user->roles;

    // Verbeterde styling voor meldingen
    $access_denied_style = 'style="padding: 20px; text-align: center; color: #fff; background-color: #EF5350; border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); font-size: 1.1em; border: 2px solid #D32F2F;"';
    $access_denied_icon = '<span style="margin-right: 10px; font-size: 1.5em; vertical-align: middle;">&#x26A0;</span>'; // Waarschuwingsicoon

    // 1. Controleer of de gebruiker de rol 'customer' heeft. Klanten mogen deze pagina niet zien.
    if (in_array('customer', $user_roles)) {
        return '<div ' . $access_denied_style . '>' . $access_denied_icon . 'U heeft geen toegang tot deze pagina.</div>';
    }

    // 2. Controleer de zichtbaarheid op basis van de ingestelde waarde en gebruikersrollen
    if ($agenda_visibility_setting === 'interne_medewerkers') {
        // Alleen beheerders mogen de pagina zien als de instelling 'interne_medewerkers' is
        if (!current_user_can('manage_options')) { // 'manage_options' is een capabiliteit die typisch hoort bij beheerders
            return '<div ' . $access_denied_style . '>' . $access_denied_icon . 'Toegang geweigerd. Alleen interne medewerkers hebben toegang.</div>';
        }
    } elseif ($agenda_visibility_setting === 'alle_werknemers') {
        // Alle ingelogde gebruikers (die geen klant zijn, wat hierboven al is gefilterd) mogen de pagina zien
        if (!is_user_logged_in()) {
            return '<div ' . $access_denied_style . '>' . $access_denied_icon . 'U moet ingelogd zijn om deze pagina te bekijken.</div>';
        }
    }
    // Einde toegangscontrolelogica

    // Haal de startdatum op uit de attributen, anders standaard naar deze week maandag.
    $atts = shortcode_atts(
        array(
            'start_date' => date('Y/m/d', strtotime('monday this week')),
        ),
        $atts,
        'wc_orders_week_agenda'
    );
	
    // **TOEGEVOEGD**: dag‐filter vanuit URL (?day=YYYY-MM-DD)
$day_filter = isset($_GET['filter_day']) 
    ? sanitize_text_field($_GET['filter_day']) 
    : '';
	
	
    $current_week_start_timestamp = strtotime($atts['start_date']);
    $week_start = strtotime('monday this week', $current_week_start_timestamp);
    $week_end = strtotime('sunday this week 23:59:59', $current_week_start_timestamp);

    $agenda = array();

    // Standaard statussen voor initiële weergave
    $wc_initial_statuses = array_keys(wc_get_order_statuses());

    // Alle statussen voor maatwerk, inclusief 'geaccepteerd' en 'afgewezen'
    $maatwerk_initial_statuses = array('nieuw', 'in-optie', 'in_behandeling', 'bevestigd', 'afgerond', 'publish', 'geannuleerd', 'geaccepteerd', 'afgewezen');

    // --- 1. Query WooCommerce bestellingen ---
    $args_wc = array(
        'post_type'      => 'shop_order',
        'posts_per_page' => -1,
        'post_status'    => $wc_initial_statuses, // Gebruik alle statussen
        'meta_query'     => array(
            array(
                'key'     => 'pi_system_delivery_date', // Correcte meta key voor leveringsdatum
                'value'   => array(date('Y/m/d', $week_start), date('Y/m/d', $week_end)),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
    );

    $wc_orders = get_posts($args_wc);

    foreach ($wc_orders as $order_post) {
        $order_id = $order_post->ID;
        $order = wc_get_order($order_id);

        if (!$order) {
            continue;
        }

        $delivery_date = get_post_meta($order_id, 'pi_system_delivery_date', true);
        $delivery_time = get_post_meta($order_id, 'pi_delivery_time', true);
        $eindtijd      = get_post_meta($order_id, 'order_eindtijd', true);
        $locatie       = get_post_meta($order_id, 'order_location', true);
        $personen      = get_post_meta($order_id, 'order_personen', true);
        $order_reference = get_post_meta($order_id, 'order_reference', true);
        $billing_company = $order->get_billing_company();
        $customer_note = $order->get_customer_note(); // Haal klantnotitie op voor indicator

        if (!$delivery_date) continue;
        if (!isset($agenda[$delivery_date])) $agenda[$delivery_date] = array();

        $agenda[$delivery_date][] = array(
            'type'                      => 'woocommerce', // Toegevoegd type voor onderscheid
            'order_id'                  => $order_id,
            'sequential_order_number'   => $order->get_order_number(),
            'tijd'                      => $delivery_time ? $delivery_time : '�',
            'eindtijd'                  => $eindtijd ? $eindtijd : '',
            'locatie'                   => $locatie,
            'personen'                  => $personen,
            'order_reference'           => $order_reference,
            'post_status'               => $order_post->post_status,
            'billing_company'           => $billing_company,
            'has_note'                  => !empty($customer_note) // Voeg 'has_note' vlag toe
        );
    }
    wp_reset_postdata(); // Reset postdata na WooCommerce query

    // --- 2. Query Maatwerk bestellingen ---
    $args_maatwerk = array(
        'post_type'      => 'maatwerk_bestelling', // Custom Post Type van Maatwerk
        'posts_per_page' => -1,
        'post_status'    => $maatwerk_initial_statuses, // Gebruik alle statussen
        'meta_query'     => array(
            array(
                'key'     => 'datum', // De meta key voor de datum in maatwerk
                'value'   => array(date('Y-m-d', $week_start), date('Y-m/d', $week_end)),
                'compare' => 'BETWEEN',
                'type'    => 'DATE' // Houd type als DATE voor juiste vergelijking
            )
        )
    );

    $maatwerk_orders = get_posts($args_maatwerk);
    error_log('DEBUG: Found ' . count($maatwerk_orders) . ' maatwerk orders for week ' . date('Y/m/d', $week_start) . ' - ' . date('Y/m/d', $week_end)); // Nieuwe debug log voor maatwerk bestellingen

    foreach ($maatwerk_orders as $m_order_post) {
        $m_order_id = $m_order_post->ID;

        // Haal de maatwerk meta-data op zoals gespecificeerd
        $order_nummer            = get_post_meta($m_order_id, 'order_nummer', true);
        $maatwerk_voornaam       = get_post_meta($m_order_id, 'maatwerk_voornaam', true);
        $maatwerk_achternaam     = get_post_meta($m_order_id, 'maatwerk_achternaam', true);
        $maatwerk_email          = get_post_meta($m_order_id, 'maatwerk_email', true);
        $maatwerk_telefoonnummer = get_post_meta($m_order_id, 'maatwerk_telefoonnummer', true);
        $bedrijfsnaam            = get_post_meta($m_order_id, 'bedrijfsnaam', true);
        $straat_huisnummer       = get_post_meta($m_order_id, 'straat_huisnummer', true);
        $postcode                = get_post_meta($m_order_id, 'postcode', true);
        $plaats                  = get_post_meta($m_order_id, 'plaats', true);
        $referentie              = get_post_meta($m_order_id, 'referentie', true);
        $datum                   = get_post_meta($m_order_id, 'datum', true); // Dit is de leveringsdatum
        $start_tijd              = get_post_meta($m_order_id, 'start_tijd', true);
        $eind_tijd               = get_post_meta($m_order_id, 'eind_tijd', true);
        $aantal_medewerkers      = get_post_meta($m_order_id, 'aantal_medewerkers', true);
        $aantal_personen         = get_post_meta($m_order_id, 'aantal_personen', true);
        $opmerkingen             = get_post_meta($m_order_id, '_maatwerk_bestelling_opmerkingen', true); // Aangepast naar de correcte meta key
        $order_status_maatwerk   = get_post_meta($m_order_id, 'order_status', true); // Haal de daadwerkelijke maatwerk order status op
        $optie_geldig_tot        = get_post_meta($m_order_id, 'optie_geldig_tot', true); // Haal de optie geldig tot datum op
		$important_opmerking 	 = get_post_meta($m_order_id, 'important_opmerking', true);

		// Mappen naar het agenda-formaat
        $delivery_date_maatwerk = $datum; // Gebruik de 'datum' meta als delivery_date

        // Robuuste datum parsing voor de agenda array key, voor het geval de opgeslagen 'datum' niet in Y-MM-DD is
        $parsed_date = DateTime::createFromFormat('Y-m-d', $datum); // Probeer Y-MM-DD
        if (!$parsed_date) {
            $parsed_date = DateTime::createFromFormat('d-m-Y', $datum); // Probeer DD-MM-YYYY
        }
        if (!$parsed_date) {
            $parsed_date = DateTime::createFromFormat('m/d/Y', $datum); // Probeer MM/DD/YYYY (Amerikaans)
        }
        if ($parsed_date) {
            $delivery_date_maatwerk = $parsed_date->format('Y/m/d'); // Zorg voor Y/MM/DD voor de agenda key
        } else {
            error_log('DEBUG: Kon maatwerk datum niet parsen: ' . $datum . ' voor order ID: ' . $m_order_id);
            continue; // Overslaan als datum niet betrouwbaar geparsed kan worden
        }


        $delivery_time_maatwerk = $start_tijd;
        $eindtijd_maatwerk      = $eind_tijd;
        $locatie_maatwerk       = (!empty($straat_huisnummer) ? $straat_huisnummer . ', ' : '') . $plaats; // Combineer straat en plaats
        $personen_maatwerk      = !empty($aantal_personen) ? $aantal_personen : (!empty($aantal_medewerkers) ? $aantal_medewerkers : ''); // Gebruik personen, anders medewerkers
        $order_reference_maatwerk = $referentie;
        $billing_company_maatwerk = $bedrijfsnaam;
        // Gebruik de daadwerkelijke maatwerk order status indien beschikbaar, anders fallback
        $display_post_status_maatwerk = !empty($order_status_maatwerk) ? $order_status_maatwerk : 'mw-completed';

        if (!$delivery_date_maatwerk) continue;
        if (!isset($agenda[$delivery_date_maatwerk])) $agenda[$delivery_date_maatwerk] = array();

$agenda[$delivery_date_maatwerk][] = array(
    'type'                      => 'maatwerk',
    'order_id'                  => $m_order_id,
    'sequential_order_number'   => !empty($order_nummer) ? $order_nummer : 'MW-' . $m_order_id,
    'tijd'                      => $delivery_time_maatwerk ? $delivery_time_maatwerk : '🕒',
    'eindtijd'                  => $eindtijd_maatwerk ? $eindtijd_maatwerk : '',
    'locatie'                   => $locatie_maatwerk,
    'personen'                  => $personen_maatwerk,
    'order_reference'           => $order_reference_maatwerk,
    'post_status'               => $display_post_status_maatwerk,
    'billing_company'           => $billing_company_maatwerk,
    'maatwerk_voornaam'         => $maatwerk_voornaam,
    'maatwerk_achternaam'       => $maatwerk_achternaam,
    'maatwerk_email'            => $maatwerk_email,
    'maatwerk_telefoonnummer'   => $maatwerk_telefoonnummer,
    'postcode'                  => $postcode,
    'opmerkingen'               => $opmerkingen,
    'important_opmerking'       => $important_opmerking,
    'aantal_medewerkers'        => $aantal_medewerkers,
    'aantal_personen_raw'       => $aantal_personen,
    'optie_geldig_tot'          => $optie_geldig_tot,
    'has_note'                  => !empty($opmerkingen)
);
    }
    wp_reset_postdata(); // Reset postdata na Maatwerk query

    // Bereken timestamps voor navigatieknoppen
    $prev_week_timestamp = strtotime('-1 week', $current_week_start_timestamp);
    $next_week_timestamp = strtotime('+1 week', $current_week_start_timestamp);

    // Start HTML output voor de weekagenda container
    $output = '<div class="wc-weekagenda-wrapper" data-agenda-visibility="' . esc_attr($agenda_visibility_setting) . '">';

    // De CSS-stijl wordt nu hier EENMALIG toegevoegd
    $output .= '<style>
.wc-nav-button.icon-only {
    display: flex; /* Maakt de knop een flex-container */
    justify-content: center; /* Centreert de inhoud (het icoon) horizontaal */
    align-items: center; /* Centreert de inhoud (het icoon) verticaal */
    padding: 8px; /* Pas deze waarde aan om de gewenste ruimte rondom het icoon te creëren */
    /* Optioneel: Stel een vaste breedte en hoogte in voor perfect vierkante knoppen */
    width: 32px; 
    height: 32px;
}

.wc-nav-button.icon-only svg {
    margin: 0 !important; /* Verwijdert eventuele resterende marges op de SVG */
}
        /* Importeer Google Font - Roboto wordt veel gebruikt in Google-producten */
        @import url("https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap");

        .wc-weekagenda-wrapper {
            max-width: 100%;
            margin: 20px auto;
            font-family: "Roboto", sans-serif;
            color: #fff;
        }

        .wc-agenda-navigation {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px 0;
            background-color: transparent;
            border-bottom: none;
        }
        .wc-nav-button {
            background-color: #4CAF50;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-left: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .wc-nav-button:first-child {
            margin-left: 0;
        }
        .wc-nav-button:hover {
            background-color: #45a049;
        }
    #wc-hide-cancelled.wc-nav-button {
        background-color: #F44336;  /* Felrood */
        color: #fff;
    }
    #wc-hide-cancelled.wc-nav-button:hover {
        background-color: #D32F2F;  /* Iets donkerder bij hover */
    }
        .wc-current-week-display {
            font-size: 1.2em;
            font-weight: 500;
            color: #fff;
            flex-grow: 1;
            text-align: center;
            margin-bottom: 0;
        }
        .wc-nav-elements-right, .wc-nav-elements-left {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .wc-nav-elements-left {
            margin-right: auto;
        }
        .wc-nav-elements-right {
            margin-left: auto;
        }

        @media (max-width: 768px) {
            .wc-agenda-navigation {
                flex-direction: column;
                align-items: center;
            }
            .wc-current-week-display {
                margin-bottom: 10px;
            }
            .wc-nav-elements-left,
            .wc-nav-elements-right {
                flex-basis: 100%;
                justify-content: center;
                margin-bottom: 10px;
                margin-left: 0;
                margin-right: 0;
            }
        }

        .wc-arrow-button {
            background-color: #4CAF50;
            border: none;
            color: #fff;
            font-size: 0.9em;
            padding: 8px 12px;
            font-weight: bold;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            margin: 0;
        }
        .wc-arrow-button:hover {
            background-color: #45a049;
            color: #fff;
        }

        .wc-today-button {
            margin-right: 10px;
        }

        .wc-refresh-button {
            background-color: #4CAF50;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wc-refresh-button:hover {
            background-color: #45a049;
        }
        .wc-refresh-button svg {
            width: 1em;
            height: 1em;
        }

        .wc-date-filter-button {
            position: relative;
            text-align: center;
            min-width: 120px;
            padding: 8px 12px;
            font-size: 0.9em;
            box-sizing: border-box;
            width: 120px;
        }
        .wc-date-filter-button::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
        }
        .wc-date-filter-button::-webkit-datetime-edit-month-field,
        .wc-date-filter-button::-webkit-datetime-edit-day-field,
        .wc-date-filter-button::-webkit-datetime-edit-year-field {
            color: transparent;
        }
        .wc-date-filter-button::before {
            content: "Selecteer Week";
            color: #fff;
            position: absolute;
            pointer-events: none;
            padding-top: 1px;
            font-weight: bold;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }
        .wc-date-filter-button:valid::before {
            content: "";
        }

        .wc-weekagenda-google {
            display: flex;
            flex-wrap: wrap;
            overflow-x: visible;
            gap: 0;
            font-family: "Roboto", sans-serif;
            color: #fff;
            padding: 0;
            background-color: transparent;
            border-radius: 8px;
            box-shadow: none;
            max-width: 100%;
            margin: 0;
            border: none;
            transition: none;
        }

        .wc-weekagenda-dag-google {
            flex-shrink: 0;
            flex-grow: 1;
            width: calc(100% / 7);
            background: transparent;
            border-radius: 0;
            padding: 16px;
            min-height: 200px;
            box-shadow: none;
            transition: none;
            border-top: none;
            border-right: 1px solid rgba(255, 255, 255, 0.3);
            border-bottom: none;
            display: flex;
            flex-direction: column;
            padding-bottom: 24px;
            box-sizing: border-box;
        }
        .wc-weekagenda-dag-google:nth-child(7n) {
            border-right: none;
        }

        .wc-weekagenda-dag-google:hover {
            transform: none;
            box-shadow: none;
            z-index: 1;
        }
        .wc-weekagenda-dag-google h4 {
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 1.3em;
            font-weight: 500;
            color: #fff;
            display: block;
            line-height: 1.2;
        }
        .wc-weekagenda-dag-google .wc-day-date {
            font-size: 0.9em;
            color: #ccc;
            margin-bottom: 10px;
            font-weight: 400;
            display: block;
        }

        .wc-order-badge {
            display: inline-block;
            color: #fff;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Maatwerk badge kleuren */
        .wc-order-badge.status-maatwerk-green {
            background-color: #4CAF50 !important;
        }
        .wc-order-badge.status-maatwerk-red {
            background-color: #F44336 !important;
        }
        .wc-order-badge.status-maatwerk-orange {
            background-color: #FF8C00 !important;
        }

        /* WooCommerce badge kleuren */
        .wc-order-badge.status-wc-green {
            background-color: #4CAF50 !important;
        }
        .wc-order-badge.status-wc-orange {
            background-color: #FF8C00 !important;
        }
        .wc-order-badge.status-wc-red {
            background-color: #F44336 !important;
        }

        .wc-weekagenda-item-google {
            background: #3B3B4C;
            margin-bottom: 8px;
            border-radius: 8px;
            padding: 10px;
            font-size: 0.9em;
            line-height: 1.4;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
            padding-bottom: 8px;
            border-bottom: none;
            cursor: pointer;
        }
        .wc-weekagenda-item-google:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        /* Nieuwe border-left stijlen per type */
        .wc-weekagenda-item-woocommerce {
            border-left: 4px solid #0000FF;
        }
        .wc-weekagenda-item-maatwerk {
            border-left: 4px solid #800080;
        }

        .wc-weekagenda-item-google b {
            color: #fff;
            font-weight: 500;
        }
        .wc-weekagenda-item-google .wc-time-display {
            font-size: 0.9em;
            font-weight: bold;
            display: block;
            margin-top: 5px;
        }
        .wc-weekagenda-item-google .wc-company-name-display {
            font-size: 0.9em;
            font-weight: normal;
            display: block;
            margin-bottom: 5px;
            color: #fff;
        }

        .wc-weekagenda-item-google a {
            color: #fff;
            text-decoration: none;
            font-weight: normal;
            transition: color 0.2s ease-in-out;
        }
        .wc-weekagenda-item-google a:hover {
            text-decoration: underline;
            color: #f0f0f0;
        }
        .wc-weekagenda-leeg-google {
            color: #fff;
            font-style: italic;
            padding: 15px;
            text-align: center;
            background: #3B3B4C;
            border-radius: 8px;
            margin-top: 15px;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wc-info-text {
            font-weight: normal;
            color: #fff;
            font-size: 0.9em;
        }
        .wc-info-text b {
            font-weight: 500;
        }

        /* Alle dagspecifieke kleuren voor border-top zijn nu overschreven naar wit */
        .maandag, .dinsdag, .woensdag, .donderdag, .vrijdag, .zaterdag, .zondag {
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Modale Stijlen */
        .wc-order-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
            padding-top: 60px;
        }

        .wc-order-modal-content {
            background-color: #3B3B4C;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 10px;
            position: relative;
            box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);
            color: #fff;
            font-family: "Roboto", sans-serif;
        }

        .wc-order-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            right: 20px;
            top: 10px;
        }

        .wc-order-modal-close:hover,
        .wc-order-modal-close:focus {
            color: #fff;
            text-decoration: none;
            cursor: pointer;
        }

        .wc-modal-top-info {
            color: #FF8C00;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
            text-align: left;
        }
        .wc-modal-top-info span {
            display: block;
            margin-bottom: 5px;
        }
        .wc-modal-top-info span:last-child {
            margin-bottom: 0;
        }

        .wc-modal-detail-section {
            margin-bottom: 15px;
            padding-bottom: 0;
            border-bottom: none;
        }

        .wc-modal-detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .wc-modal-detail-section h4 {
            color: #FF8C00;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1em;
            padding-bottom: 5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .wc-modal-detail-section p {
            margin: 0;
            font-size: 0.95em;
            line-height: 1.4;
            padding-top: 5px;
        }
        .wc-modal-detail-section p.no-title {
            font-weight: normal;
        }
        .wc-modal-detail-section p.no-title strong {
            font-weight: bold;
        }
        .wc-modal-detail-section p + p {
            margin-top: 5px;
        }

        .wc-modal-detail-section.ordered-products ul {
            list-style: none;
            padding: 0;
            margin: 0;
            border-top: none;
        }

        .wc-modal-detail-section.ordered-products ul li {
            background-color: transparent;
            padding: 10px 0;
            margin-bottom: 0;
            border-radius: 0;
            font-size: 0.9em;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .wc-modal-detail-section.ordered-products ul li:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .wc-modal-detail-section.ordered-products ul li .product-name-qty {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 5px;
        }
        .wc-modal-detail-section.ordered-products ul li ul {
            padding-left: 15px;
            margin-top: 5px;
            width: 100%;
            border-top: none;
        }
        .wc-modal-detail-section.ordered-products ul li ul li {
            background-color: transparent;
            padding: 0;
            border-bottom: none;
            margin-bottom: 2px;
            font-size: 0.85em;
        }

.wc-daily-summary-button {
    background-color: #3B3B4C !important;   /* Heldere blauw */
    color: #fff !important;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.8em;
    font-weight: bold;
    transition: background-color 0.3s ease;
    width: 100%;
    margin-top: auto;
    display: block;
    text-align: center;
}

.wc-daily-summary-button:hover {
    background-color: #3B3B4C !important;   /* Iets donkerder blauw bij hover */
}



        /* Note indicator for order items */
        .wc-weekagenda-item-google[data-has-note="true"] {
            position: relative; /* Nodig voor positionering van pseudo-element */
        }
        .wc-weekagenda-item-google[data-has-note="true"]::after {
            content: "!"; /* Uitroepteken */
            display: block;
            width: 20px;
            height: 20px;
            background-color: #FF8C00; /* Oranje */
            color: #fff;
            border-radius: 50%; /* Ronde vorm */
            text-align: center;
            line-height: 20px; /* Centreer tekst verticaal */
            font-weight: bold;
            font-size: 1.1em;
            position: absolute;
            top: 5px; /* Pas aan indien nodig */
            right: 5px; /* Pas aan indien nodig */
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 10; /* Zorg ervoor dat het boven andere elementen ligt */
        }


        @media (max-width: 600px) {
            .wc-order-modal-content {
                width: 95%;
                margin: 20px auto;
            }
        }
    </style>';

$output .= '<div class="wc-agenda-navigation">';
// Groepeer Maatwerk, Klant toevoegen en Turflijst knoppen aan de linkerkant
$output .= '<div class="wc-nav-elements-left">';
// Toon "+ Maatwerk" en "Klant toevoegen" knoppen alleen aan beheerders
    $output .= '<button id="wc-filter-wc" class="wc-nav-button">Toon alleen banqueting</button>';
	$output .= '<button id="wc-hide-cancelled" class="wc-nav-button">Verberg geannuleerd</button>';

if (in_array('administrator', $user_roles)) { 
    $output .= '<button class="wc-nav-button wc-new-maatwerk-button icon-only">'; // 'icon-only' klasse toegevoegd
    $output .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 14px; height: 14px; vertical-align: middle;"><path fill="currentColor" d="M96 32l0 32L48 64C21.5 64 0 85.5 0 112l0 48 448 0 0-48c0-26.5-21.5-48-48-48l-48 0 0-32c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 32L160 64l0-32c0-17.7-14.3-32-32-32S96 14.3 96 32zM448 192L0 192 0 464c0 26.5 21.5 48 48 48l352 0c26.5 0 48-21.5 48-48l0-272zM224 248c13.3 0 24 10.7 24 24l0 56 56 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-56 0 0 56c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-56-56 0c-13.3 0-24-10.7-24-24s10.7-24 24-24l56 0 0-56c0-13.3 10.7-24 24-24z"/></svg>';
    $output .= '</button>'; // Tekst "Maatwerk" is verwijderd

    $output .= '<button class="wc-nav-button wc-add-customer-button icon-only">'; // 'icon-only' klasse toegevoegd
    $output .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" style="width: 14px; height: 14px; vertical-align: middle;"><path fill="currentColor" d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304l91.4 0C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7L29.7 512C13.3 512 0 498.7 0 482.3zM504 312l0-64-64 0c-13.3 0-24-10.7-24-24s10.7-24 24-24l64 0 0-64c0-13.3 10.7-24 24-24s24 10.7 24 24l0 64 64 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-64 0 0 64c0 13.3-10.7 24-24 24s-24-10.7-24-24z"/></svg>';
    $output .= '</button>'; // Tekst "Klant toevoegen" is verwijderd
}

$output .= '<button class="wc-nav-button wc-turflijst-button icon-only">'; // Added 'icon-only' class
$output .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 14px; height: 14px; vertical-align: middle;"><path fill="currentColor" d="M152.1 38.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.4 4.9-10.6 7.8-17.2 7.9s-12.9-2.4-17.6-7L7 113C-2.3 103.6-2.3 88.4 7 79s24.6-9.4 33.9 0l22.1 22.1 55.1-61.2c8.9-9.9 24-10.7 33.9-1.8zm0 160c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.4 4.9-10.6 7.8-17.2 7.9s-12.9-2.4-17.6-7L7 273c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l22.1 22.1 55.1-61.2c8.9-9.9 24-10.7 33.9-1.8zM224 96c0-17.7 14.3-32 32-32l224 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-224 0c-17.7 0-32-14.3-32-32zm0 160c0-17.7 14.3-32 32-32l224 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-224 0c-17.7 0-32-14.3-32-32zM160 416c0-17.7 14.3-32 32-32l288 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-288 0c-17.7 0-32-14.3-32-32zM48 368a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></svg>';
$output .= '</button>'; 
	
    $output .= '</div>'; // Sluit wc-nav-elements-left

    $output .= '<span class="wc-current-week-display">' . date_i18n('j F Y', $week_start) . ' - ' . date_i18n('j F Y', $week_end) . '</span>';
    // Groepeer navigatieknoppen en datumfilter aan de rechterkant
    $output .= '<div class="wc-nav-elements-right">';

// Home knop:
$output .= '<button class="wc-nav-button wc-help-button" title="Home" type="button" onclick="window.location.href=\'https://banquetingportaal.nl/alg/orders/\';">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M575.8 255.5c0 18-15 32.1-32 32.1l-32 0 .7 160.2c0 2.7-.2 5.4-.5 8.1l0 16.2c0 22.1-17.9 40-40 40l-16 0c-1.1 0-2.2 0-3.3-.1c-1.4 .1-2.8 .1-4.2 .1L416 512l-24 0c-22.1 0-40-17.9-40-40l0-24 0-64c0-17.7-14.3-32-32-32l-64 0c-17.7 0-32 14.3-32 32l0 64 0 24c0 22.1-17.9 40-40 40l-24 0-31.9 0c-1.5 0-3-.1-4.5-.2c-1.2 .1-2.4 .2-3.6 .2l-16 0c-22.1 0-40-17.9-40-40l0-112c0-.9 0-1.9 .1-2.8l0-69.7-32 0c-18 0-32-14-32-32.1c0-9 3-17 10-24L266.4 8c7-7 15-8 22-8s15 2 21 7L564.8 231.5c8 7 12 15 11 24z"/></svg>
</button>';

    // Nieuwe "Vandaag" knop
    $output .= '<button class="wc-nav-button wc-today-button" data-week-start="' . date('Y/m/d', strtotime('monday this week')) . '">Deze week</button>';
    // Pijlknoppen
    $output .= '<button class="wc-nav-button wc-arrow-button" data-week-start="' . date('Y/m/d', $prev_week_timestamp) . '">&lt;</button>';
    $output .= '<button class="wc-nav-button wc-arrow-button" data-week-start="' . date('Y/m/d', $next_week_timestamp) . '">&gt;</button>';
    // Datumkiezer achter de pijltjes
    $output .= '<input type="date" id="wc-date-filter" class="wc-nav-button wc-date-filter-button" value="' . date('Y-m-d', $current_week_start_timestamp) . '">';
    $output .= '</div>'; // Sluit wc-nav-elements-right

    $output .= '</div>'; // Sluit wc-agenda-navigation

    // De agenda-container die via AJAX wordt bijgewerkt
    $output .= '<div class="wc-weekagenda-google" id="wc-weekagenda-container">';
    // Geef de initiële has_note status door aan generate_agenda_content
    $output .= generate_agenda_content($agenda, $week_start, $day_filter);
    $output .= '</div>'; // Sluit wc-weekagenda-google
    $output .= '</div>'; // Sluit wc-weekagenda-wrapper

    // Modale pop-up structuur
    $output .= '
    <div id="wc-order-modal" class="wc-order-modal">
        <div class="wc-order-modal-content">
            <span class="wc-order-modal-close">&times;</span>
            <div id="wc-order-modal-body">
                <div style="text-align: center; padding: 20px;">Laden bestelgegevens...</div>
            </div>
        </div>
    </div>';

    // JavaScript voor AJAX navigatie en modal
    $output .= '
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    initAgendaNavigation();
    initModalFunctionality();
    initNewMaatwerkButton();
    initAddCustomerButton();
    initTurflijstButton();
    initRefreshButton();
    initFilters();
    initDailySummaryButtons();

    // **TOEGEVOEGD**: filter WooCommerce-only
    const filterWcBtn = document.getElementById("wc-filter-wc");
    filterWcBtn.dataset.active = "0";
    filterWcBtn.addEventListener("click", function() {
        const items = document.querySelectorAll(".wc-weekagenda-item-google");
        const showAll = this.dataset.active === "1";
        items.forEach(item => {
            if (item.dataset.orderType === "maatwerk") {
                item.style.display = showAll ? "" : "none";
            }
        });
        this.textContent   = showAll ? "Toon alleen banqueting" : "Toon alle orders";
        this.dataset.active = showAll ? "0" : "1";
    });

    // **TOEGEVOEGD**: filter geannuleerde WooCommerce‑orders
    const hideCancelledBtn = document.getElementById("wc-hide-cancelled");
    hideCancelledBtn.dataset.active = "0";
    hideCancelledBtn.addEventListener("click", function() {
        const items = document.querySelectorAll(".wc-weekagenda-item-google");
        const showAll = this.dataset.active === "1";
        items.forEach(item => {
            if (item.dataset.orderType === "woocommerce" && item.dataset.postStatus === "wc-cancelled") {
                item.style.display = showAll ? "" : "none";
            }
        });
        this.textContent   = showAll ? "Verberg geannuleerd" : "Toon geannuleerd";
        this.dataset.active = showAll ? "0" : "1";
    });
});

function initAgendaNavigation() {
    const agendaContainer = document.getElementById("wc-weekagenda-container");
    const navButtons = document.querySelectorAll(".wc-nav-elements-right .wc-nav-button:not(.wc-date-filter-button)");
    const currentWeekDisplay = document.querySelector(".wc-current-week-display");
    const agendaVisibility = document.querySelector(".wc-weekagenda-wrapper").dataset.agendaVisibility;

    navButtons.forEach(button => {
        button.removeEventListener("click", handleNavButtonClick);
        button.addEventListener("click", handleNavButtonClick);
    });

    function handleNavButtonClick() {
        const newWeekStart = this.dataset.weekStart;
        fetchAgenda(newWeekStart, agendaVisibility);
    }

    function fetchAgenda(startDate, agendaVisibility) {
        if (agendaContainer) {
            agendaContainer.innerHTML = \'<div style="text-align:center;padding:50px;color:#fff;">Laden...</div>\';
        }

        const formData = new FormData();
        formData.append("action", "wc_orders_week_agenda_ajax");
        formData.append("start_date", startDate);
        formData.append("agenda_visibility_setting", agendaVisibility);

        fetch("' . admin_url('admin-ajax.php') . '", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            if (agendaContainer) {
                agendaContainer.innerHTML = html;

                const start = new Date(startDate);
                const end = new Date(start);
                end.setDate(end.getDate() + 6);
                const opts = { day: "numeric", month: "long", year: "numeric" };
                currentWeekDisplay.textContent =
                    start.toLocaleDateString("nl-NL", opts) + " - " +
                    end.toLocaleDateString("nl-NL", opts);

                initModalFunctionality();
                initNewMaatwerkButton();
                initAddCustomerButton();
                initTurflijstButton();
                initRefreshButton();
                initFilters();
                initDailySummaryButtons();
            }
        })
        .catch(() => {
            if (agendaContainer) {
                agendaContainer.innerHTML = \'<div style="text-align:center;padding:50px;color:#fff;">Fout bij het laden van de agenda.</div>\';
            }
        });
    }

    window.fetchAgenda = fetchAgenda;
}

function initModalFunctionality() {
    const modal = document.getElementById("wc-order-modal");
    const closeBtn = document.querySelector(".wc-order-modal-close");
    const modalBody = document.getElementById("wc-order-modal-body");
    const agendaVisibility = document.querySelector(".wc-weekagenda-wrapper").dataset.agendaVisibility;

    document.querySelectorAll(".wc-weekagenda-item-google").forEach(item => {
        item.removeEventListener("click", handleOrderItemClick);
        item.addEventListener("click", handleOrderItemClick);
    });

    function handleOrderItemClick() {
        const orderId = this.dataset.orderId;
        const orderType = this.dataset.orderType;
        modal.style.display = "block";
        modalBody.innerHTML = \'<div style="text-align:center;padding:20px;">Laden bestelgegevens...</div>\';

        const formData = new FormData();
        formData.append("action", "wc_get_order_details_ajax");
        formData.append("order_id", orderId);
        formData.append("order_type", orderType);
        formData.append("agenda_visibility_setting", agendaVisibility);

        fetch("' . admin_url('admin-ajax.php') . '", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            modalBody.innerHTML = data.success ? data.data :
                \'<div style="text-align:center;padding:20px;color:red;">\' + data.data + \'</div>\';
        })
        .catch(() => {
            modalBody.innerHTML = \'<div style="text-align:center;padding:20px;color:red;">Fout bij het laden van bestelgegevens.</div>\';
        });
    }

    closeBtn.onclick = () => modal.style.display = "none";
    window.onclick = e => { if (e.target == modal) modal.style.display = "none"; };
}

function initNewMaatwerkButton() {
    const btn = document.querySelector(".wc-new-maatwerk-button");
    if (!btn) return;
    btn.removeEventListener("click", openMaatwerk);
    btn.addEventListener("click", openMaatwerk);
    function openMaatwerk(e) {
        e.preventDefault();
        window.open("https://banquetingportaal.nl/alg/maatwerk", "_blank",
            "width=800,height=800,top="+((screen.height/2)-(800/2))+",left="+((screen.width/2)-(800/2)));
    }
}

function initAddCustomerButton() {
    const btn = document.querySelector(".wc-add-customer-button");
    if (!btn) return;
    btn.removeEventListener("click", openAdd);
    btn.addEventListener("click", openAdd);
    function openAdd(e) {
        e.preventDefault();
        window.open("https://banquetingportaal.nl/alg/add-klant/", "_blank",
            "width=800,height=800,top="+((screen.height/2)-(800/2))+",left="+((screen.width/2)-(800/2)));
    }
}

function initTurflijstButton() {
    const btn = document.querySelector(".wc-turflijst-button");
    if (!btn) return;
    btn.removeEventListener("click", openTurf);
    btn.addEventListener("click", openTurf);
    function openTurf(e) {
        e.preventDefault();
        window.open("https://banquetingportaal.nl/alg/turf/", "_blank",
            "width=800,height=800,top="+((screen.height/2)-(800/2))+",left="+((screen.width/2)-(800/2)));
    }
}

function initRefreshButton() {
    const btn = document.querySelector(".wc-refresh-button");
    if (!btn) return;
    btn.removeEventListener("click", () => {});
    btn.addEventListener("click", e => { e.preventDefault(); location.reload(); });
}

function initFilters() {
    const dateFilter = document.getElementById("wc-date-filter");
    dateFilter.removeEventListener("change", handleDateFilter);
    dateFilter.addEventListener("change", handleDateFilter);
    function handleDateFilter() {
        const d = new Date(this.value+"T00:00:00");
        const day = (d.getDay()+6)%7;
        d.setDate(d.getDate() - day);
        const mon = d.getFullYear()+"/"+String(d.getMonth()+1).padStart(2,"0")+"/"+String(d.getDate()).padStart(2,"0");
        fetchAgenda(mon, document.querySelector(".wc-weekagenda-wrapper").dataset.agendaVisibility);
    }
}

function initDailySummaryButtons() {
    const modal = document.getElementById("wc-order-modal");
    const body = document.getElementById("wc-order-modal-body");
    document.querySelectorAll(".wc-daily-summary-button").forEach(btn => {
        btn.removeEventListener("click", handleSummary);
        btn.addEventListener("click", handleSummary);
    });
    function handleSummary() {
        const day = this.dataset.dayDate;
        modal.style.display = "block";
        body.innerHTML = \'<div style="text-align:center;padding:20px;">Laden dagoverzicht...</div>\';
        const fd = new FormData();
        fd.append("action","wc_get_daily_product_summary_ajax");
        fd.append("day_date",day);
        fetch("' . admin_url('admin-ajax.php') . '", { method:"POST", body:fd })
        .then(r=>r.json())
        .then(d=> body.innerHTML = d.success? d.data : \'<div style="text-align:center;color:red;">\' + d.data + \'</div>\')
        .catch(()=> body.innerHTML = \'<div style="text-align:center;color:red;">Fout bij laden dagoverzicht.</div>\');
    }
}
</script>
';

    return $output;
}

/**
 * Genereert de agenda-inhoud (dagen en items) inclusief de styling.
 * Deze functie wordt aangeroepen door de shortcode en de AJAX-handler.
 *
 * @param array $agenda De georganiseerde bestelgegevens.
 * @param int $week_start_timestamp De timestamp van het begin van de week.
 * @return string De HTML output van de agenda-inhoud en styling.
 */
function generate_agenda_content($agenda, $week_start_timestamp, $day_filter = '') {

    $output = '';

    $dagen_data = array(
        'maandag'   => array('emoji' => ''),
        'dinsdag'   => array('emoji' => ''),
        'woensdag'  => array('emoji' => ''),
        'donderdag' => array('emoji' => ''),
        'vrijdag'   => array('emoji' => ''),
        'zaterdag'  => array('emoji' => ''),
        'zondag'    => array('emoji' => '')
    );
    $dagen_keys = array_keys($dagen_data);

    for ($i = 0; $i < 7; $i++) {
        $day_ts = strtotime("+$i day", $week_start_timestamp);
        $day_key = date('Y/m/d', $day_ts);
		$day_link = date('Y-m-d', $day_ts);
        $dagnaam = $dagen_keys[$i];
		if ($day_filter && $day_filter !== $day_link) {
				continue;
		}
        $datum   = date('d-m-Y', $day_ts);
        $emoji   = $dagen_data[$dagnaam]['emoji'];

// Bouw eerst de huidige URL (path + query) en verwijder oude 'day'
$current_url = ( is_ssl() ? 'https://' : 'http://' )
             . $_SERVER['HTTP_HOST']
             . $_SERVER['REQUEST_URI'];
$base_url    = remove_query_arg( 'filter_day', $current_url );
$link        = esc_url( add_query_arg( 'filter_day', $day_link, $base_url ) );

		
// Render klikbare dagnaam
$output .= '<div class="wc-weekagenda-dag-google" data-day-index="'. $i .'">';
$output .= '<h4><a href="'. $link .'">'
         . ucfirst($dagnaam)
         . '</a></h4>';
		
		
        $output .= '<p class="wc-day-date">' . $datum . '</p>'; // Datum onder de dagnaam

        $has_woocommerce_orders = false; // Vlag om te controleren of er WC orders zijn voor deze dag

        if (isset($agenda[$day_key])) {
            // Sorteer bestellingen op tijd, ongeacht type
            usort($agenda[$day_key], function($a, $b) {
                $time_a = ($a['tijd'] === '🕒') ? '00:00' : $a['tijd'];
                $time_b = ($b['tijd'] === '🕒') ? '00:00' : $b['tijd'];
                return strcmp($time_a, $time_b);
            });

            foreach ($agenda[$day_key] as $item) {
                if ($item['type'] === 'woocommerce') {
                    $has_woocommerce_orders = true; // Stel de vlag in als er een WC order is
                }

                // Bepaal de badge klasse op basis van de orderstatus EN het type
                $badge_class = 'wc-order-badge';
                $item_type_class = ''; // Nieuwe variabele voor de item type klasse
                $order_number_display = esc_html($item['sequential_order_number']); // Standaard weergave
                $option_info_badge = ''; // Variabele voor "In optie tot" informatie in badge

                if ($item['type'] === 'woocommerce') {
                    $item_type_class = 'wc-weekagenda-item-woocommerce'; // Blauwe streep
                    error_log('DEBUG: WooCommerce Order ID: ' . $item['order_id'] . ' Post Status: ' . $item['post_status']); // DEBUG LOG
                    if ($item['post_status'] === 'wc-processing' || $item['post_status'] === 'wc-completed') { // Processing en Completed zijn groen
                        $badge_class .= ' status-wc-green';
                    } else if ($item['post_status'] === 'wc-on-hold') { // On-hold blijft oranje
                        $badge_class .= ' status-wc-orange';
                    } else if ($item['post_status'] === 'wc-cancelled') {
                        $badge_class .= ' status-wc-red';
                    }
                } else if ($item['type'] === 'maatwerk') {
                    $item_type_class = 'wc-weekagenda-item-maatwerk'; // Paarse streep
                    $maatwerk_status = $item['post_status'];
                    $optie_geldig_tot = isset($item['optie_geldig_tot']) ? $item['optie_geldig_tot'] : '';


                    // DEBUG LOG: Log de maatwerk status en optie_geldig_tot
                    error_log('DEBUG: Maatwerk Order ID: ' . $item['order_id'] . ' Status: ' . $maatwerk_status . ' Optie geldig tot: ' . $optie_geldig_tot); // DEBUG LOG

                    // Formatteer de maatwerk status voor weergave
                    $formatted_maatwerk_status = ucfirst(str_replace('_', ' ', $maatwerk_status));

                    // Nieuwe logica voor maatwerk statussen: 'geaccepteerd' en 'afgewezen' worden nu ook groen/rood
                    if ($maatwerk_status === 'in_behandeling' || $maatwerk_status === 'bevestigd' || $maatwerk_status === 'afgerond' || $maatwerk_status === 'geaccepteerd') {
                        $badge_class .= ' status-maatwerk-green'; // Groen voor in behandeling, bevestigd, afgerond en geaccepteerd
                    } else if ($maatwerk_status === 'geannuleerd' || $maatwerk_status === 'afgewezen') {
                        $badge_class .= ' status-maatwerk-red'; // Rood voor geannuleerd en afgewezen
                    } else if ($maatwerk_status === 'in-optie' || $maatwerk_status === 'nieuw') {
                        $badge_class .= ' status-maatwerk-orange';

                        // Toon "in optie tot" informatie in de badge
                        if ($maatwerk_status === 'in-optie' && !empty($optie_geldig_tot)) {
                            $option_date_parsed = null;
                            // Probeer verschillende datumformaten
                            if (DateTime::createFromFormat('Y-m-d', $optie_geldig_tot)) {
                                $option_date_parsed = DateTime::createFromFormat('Y-m-d', $optie_geldig_tot);
                            } elseif (DateTime::createFromFormat('d-m-Y', $optie_geldig_tot)) {
                                $option_date_parsed = DateTime::createFromFormat('d-m-Y', $optie_geldig_tot);
                            } elseif (DateTime::createFromFormat('m/d/Y', $optie_geldig_tot)) {
                                $option_date_parsed = DateTime::createFromFormat('m/d/Y', $optie_geldig_tot);
                            }

                            if ($option_date_parsed) {
                                $today = new DateTime();
                                $interval = $today->diff($option_date_parsed);
                                $days_diff = (int)$interval->format('%r%a'); // %r voor teken, %a voor dagen zonder teken

                                if ($days_diff > 0) {
                                    $option_info_badge = ' (Nog ' . $days_diff . ' dag' . ($days_diff > 1 ? 'en' : '') . ')';
                                } elseif ($days_diff === 0) {
                                    $option_info_badge = ' (Laatste dag)';
                                } else {
                                    $days_elapsed = abs($days_diff);
                                    $option_info_badge = ' (' . $days_elapsed . ' dag' . ($days_elapsed > 1 ? 'en' : '') . ' verlopen)';
                                }
                            }
                        }
                    } else {
                        // Fallback voor andere onbekende maatwerk statussen (standaard oranje)
                        $badge_class .= ' status-maatwerk-orange';
                    }
                    // Ordernummer en eventuele optie info voor de badge
                    $order_number_display = esc_html($item['sequential_order_number']) . $option_info_badge;
                }

                // Tijdopmaaklogica (Vroegste Start - Laatste Eind)
                $all_times = [];
                if (!empty($item['tijd']) && $item['tijd'] !== '🕒') {
                    $parts = explode(' - ', $item['tijd']);
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if (strpos($part, ':') !== false) {
                            $all_times[] = $part;
                        }
                    }
                }
                if (!empty($item['eindtijd'])) {
                    $parts = explode(' - ', $item['eindtijd']);
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if (strpos($part, ':') !== false) {
                            $all_times[] = $part;
                        }
                    }
                }
                $all_times = array_unique($all_times);
                usort($all_times, function($a, $b) {
                    return strtotime($a) - strtotime($b);
                });

                $display_time_range = '';
                if (!empty($all_times)) {
                    $earliest_time = $all_times[0];
                    $latest_time = end($all_times);
                    $display_time_range = esc_html($earliest_time) . ' - ' . esc_html($latest_time);
                } else if ($item['tijd'] === '🕒') {
                    $display_time_range = '🕒';
                }

                // Belangrijk: Voeg data-order-type en data-has-note toe aan het item div
$output .= '<div class="wc-weekagenda-item-google ' . esc_attr($item_type_class) . '" '
         . 'data-order-id="'     . esc_attr($item['order_id'])     . '" '
         . 'data-order-type="'   . esc_attr($item['type'])         . '" '
         . 'data-post-status="'  . esc_attr($item['post_status'])  . '"'
         . (!empty($item['has_note']) ? ' data-has-note="true"' : '')
         . '>';

                // Ordernummer in badge bovenaan (gebruik sequential_order_number en optie info)
                $output .= '<div class="'.esc_attr($badge_class).'">' . $order_number_display . '</div>';

                // Bedrijfsnaam weergeven indien beschikbaar (zowel voor WC als Maatwerk)
                if (!empty($item['billing_company'])) {
                    $output .= '<span class="wc-company-name-display">' . esc_html($item['billing_company']) . '</span>';
                }

// Tijd met klok-emoji
$output .= '<span class="wc-time-display">🕒 ' . $display_time_range . '</span>';

// Locatie: alleen straat+huisnummer, of fallback 'Niet opgegeven'
if ( ! empty( $item['locatie'] ) ) {
    $parts   = explode( ', ', $item['locatie'] );
    $address = reset( $parts );
} else {
    $address = 'Niet opgegeven';
}
$output .= '<span class="wc-info-text"> 📍 ' . esc_html( $address ) . '</span>';


// Personen op eigen regel - verbergt als de waarde 0 of leeg is
if (!empty($item['personen']) && (int)$item['personen'] > 0) {
    $output .= '<br><span class="wc-info-text">👥 <b>' . esc_html($item['personen']) . '</b> Personen</span>';
}

// Medewerkers op eigen regel, alleen bij >0, met enkelvoud/meervoud
if (! empty( $item['aantal_medewerkers'] ) && intval( $item['aantal_medewerkers'] ) > 0 ) {
    $count = intval( $item['aantal_medewerkers'] );
    $label = $count === 1 ? 'Medewerker' : 'Medewerkers';
    $output .= '<br><span class="wc-info-text">👷 <b>' . esc_html( $count ) . '</b> ' . esc_html( $label ) . '</span>';
}

// Belangrijke Opmerking (alleen bij maatwerk en niet-leeg)
if ( $item['type'] === 'maatwerk' ) {
    $output .= '<br><div class="wc-info-text"><strong>✏️ </strong>'
             . esc_html( $item['important_opmerking'] )
             . '</div>';
}
				                $output .= '<div class="wc-pakbon-button" style="margin:8px 0;">'
                         . do_shortcode(
                             '[wcpdf_download_pdf document_type="packing-slip" '
                           . 'order_id="' . esc_attr( $item['order_id'] ) . '" '
                           . 'link_text="📄 keuken order bekijken"]'
                         )
                         . '</div>';
                $output .= '</div>';
            }
        } else {
            $output .= '<div class="wc-weekagenda-leeg-google">Geen bestellingen vandaag! </div>';
        }

        // Voeg de "Bekijk Dagoverzicht Producten" knop toe als er WooCommerce orders zijn voor deze dag
        if ($has_woocommerce_orders) {
            $output .= '<br><button class="wc-daily-summary-button" data-day-date="' . esc_attr($day_key) . '">Dagtotaal</button>';
        }
 		else {
            $output .= '<br><button class="wc-daily-summary-button" data-day-date="' . esc_attr($day_key) . '">Dagtotaal</button>';
        }

        $output .= '</div>';
    }
    return $output;
}

/**
 * AJAX handler om de agenda-inhoud dynamisch te laden.
 */
add_action('wp_ajax_wc_orders_week_agenda_ajax', 'wc_orders_week_agenda_ajax_handler');
add_action('wp_ajax_nopriv_wc_orders_week_agenda_ajax', 'wc_orders_week_agenda_ajax_handler');

function wc_orders_week_agenda_ajax_handler() {
    error_log('wc_orders_week_agenda_ajax_handler function called.'); // Debug log
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y/m/d', strtotime('monday this week'));
    $agenda_visibility_setting = isset($_POST['agenda_visibility_setting']) ? sanitize_text_field($_POST['agenda_visibility_setting']) : 'alle_werknemers'; // Haal zichtbaarheidsinstelling op van AJAX

    $current_week_start_timestamp = strtotime($start_date);
    $week_start = strtotime('monday this week', $current_week_start_timestamp);
    $week_end = strtotime('sunday this week 23:59:59', $current_week_start_timestamp);

    $agenda = array();

    // --- 1. Query WooCommerce bestellingen ---
    $wc_post_statuses = array_keys(wc_get_order_statuses());

    $args_wc = array(
        'post_type'      => 'shop_order',
        'posts_per_page' => -1,
        'post_status'    => $wc_post_statuses,
        'meta_query'     => array(
            array(
                'key'     => 'pi_system_delivery_date',
                'value'   => array(date('Y/m/d', $week_start), date('Y/m/d', $week_end)),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
    );

    $wc_orders = get_posts($args_wc);
    foreach ($wc_orders as $order_post) {
        $order_id = $order_post->ID;
        $order = wc_get_order($order_id);
        if (!$order) continue;
        $delivery_date = get_post_meta($order_id, 'pi_system_delivery_date', true);
        $delivery_time = get_post_meta($order_id, 'pi_delivery_time', true);
        $eindtijd      = get_post_meta($order_id, 'order_eindtijd', true);
        $locatie       = get_post_meta($order_id, 'order_location', true);
        $personen      = get_post_meta($order_id, 'order_personen', true);
        $order_reference = get_post_meta($order_id, 'order_reference', true);
        $billing_company = $order->get_billing_company();
        $customer_note = $order->get_customer_note(); // Haal klantnotitie op voor indicator
        if (!$delivery_date) continue;
        if (!isset($agenda[$delivery_date])) $agenda[$delivery_date] = array();
        $agenda[$delivery_date][] = array(
            'type'                      => 'woocommerce',
            'order_id'                  => $order_id,
            'sequential_order_number'   => $order->get_order_number(),
            'tijd'                      => $delivery_time ? $delivery_time : '🕒',
            'eindtijd'                  => $eindtijd ? $eindtijd : '',
            'locatie'                   => $locatie,
            'personen'                  => $personen,
            'order_reference'           => $order_reference,
            'post_status'               => $order_post->post_status,
            'billing_company'           => $billing_company,
            'has_note'                  => !empty($customer_note) // Voeg 'has_note' vlag toe
        );
    }
    wp_reset_postdata(); // Reset postdata na WooCommerce query in AJAX

    // --- 2. Query Maatwerk bestellingen ---
    // Alle statussen voor maatwerk, inclusief 'geaccepteerd' en 'afgewezen'
    $maatwerk_post_statuses = array('nieuw', 'in-optie', 'in_behandeling', 'bevestigd', 'afgerond', 'publish', 'geannuleerd', 'geaccepteerd', 'afgewezen');

    $args_maatwerk = array(
        'post_type'      => 'maatwerk_bestelling',
        'posts_per_page' => -1,
        'post_status'    => $maatwerk_post_statuses,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => 'datum',
                'value'   => array(date('Y-m-d', $week_start), date('Y-m-d', $week_end)),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
    );

    $maatwerk_orders = get_posts($args_maatwerk);
    error_log('DEBUG: Found ' . count($maatwerk_orders) . ' maatwerk orders (AJAX) for week ' . date('Y/m/d', $week_start) . ' - ' . date('Y/m/d', $week_end)); // Nieuwe debug log

    foreach ($maatwerk_orders as $m_order_post) {
        $m_order_id = $m_order_post->ID;
        $order_nummer            = get_post_meta($m_order_id, 'order_nummer', true);
        $maatwerk_voornaam       = get_post_meta($m_order_id, 'maatwerk_voornaam', true);
        $maatwerk_achternaam     = get_post_meta($m_order_id, 'maatwerk_achternaam', true);
        $maatwerk_email          = get_post_meta($m_order_id, 'maatwerk_email', true);
        $maatwerk_telefoonnummer = get_post_meta($m_order_id, 'maatwerk_telefoonnummer', true);
        $bedrijfsnaam            = get_post_meta($m_order_id, 'bedrijfsnaam', true);
        $straat_huisnummer       = get_post_meta($m_order_id, 'straat_huisnummer', true);
        $postcode                = get_post_meta($m_order_id, 'postcode', true);
        $plaats                  = get_post_meta($m_order_id, 'plaats', true);
        $referentie              = get_post_meta($m_order_id, 'referentie', true);
        $datum                   = get_post_meta($m_order_id, 'datum', true);
        $start_tijd              = get_post_meta($m_order_id, 'start_tijd', true);
        $eind_tijd               = get_post_meta($m_order_id, 'eind_tijd', true);
        $aantal_medewerkers      = get_post_meta($m_order_id, 'aantal_medewerkers', true);
        $aantal_personen         = get_post_meta($m_order_id, 'aantal_personen', true);
		$important_opmerking = get_post_meta($m_order_id, 'important_opmerking', true);
        $opmerkingen             = get_post_meta($m_order_id, '_maatwerk_bestelling_opmerkingen', true); // Aangepast naar de correcte meta key
        $order_status_maatwerk   = get_post_meta($m_order_id, 'order_status', true); // Haal de daadwerkelijke maatwerk order status op
        $optie_geldig_tot        = get_post_meta($m_order_id, 'optie_geldig_tot', true); // Haal de optie geldig tot datum op
        error_log('DEBUG: Maatwerk Order ID: ' . $m_order_id . ' Status: ' . $order_status_maatwerk . ' Datum: ' . $datum . ' Optie geldig tot: ' . $optie_geldig_tot); // Log status en datum

        $delivery_date_maatwerk = $datum;
        $delivery_time_maatwerk = $start_tijd;
        $eindtijd_maatwerk      = $eind_tijd;
        $locatie_maatwerk       = (!empty($straat_huisnummer) ? $straat_huisnummer . ', ' : '') . $plaats;
        $personen_maatwerk      = !empty($aantal_personen) ? $aantal_personen : (!empty($aantal_medewerkers) ? $aantal_medewerkers : '');
        $order_reference_maatwerk = $referentie;
        $billing_company_maatwerk = $bedrijfsnaam;
        $display_post_status_maatwerk = !empty($order_status_maatwerk) ? $order_status_maatwerk : 'mw-completed';

        // Robuuste datum parsing voor de agenda array key
        $parsed_date = DateTime::createFromFormat('Y-m-d', $datum);
        if (!$parsed_date) {
            $parsed_date = DateTime::createFromFormat('d-m-Y', $datum);
        }
        if (!$parsed_date) {
            $parsed_date = DateTime::createFromFormat('m/d/Y', $datum);
        }
        if ($parsed_date) {
            $delivery_date_maatwerk = $parsed_date->format('Y/m/d');
        } else {
            error_log('DEBUG: Kon maatwerk datum niet parsen (AJAX): ' . $datum . ' voor order ID: ' . $m_order_id);
            continue;
        }

        if (!$delivery_date_maatwerk) continue;
        if (!isset($agenda[$delivery_date_maatwerk])) $agenda[$delivery_date_maatwerk] = array();
        $agenda[$delivery_date_maatwerk][] = array(
            'type'                      => 'maatwerk',
            'order_id'                  => $m_order_id,
            'sequential_order_number'   => !empty($order_nummer) ? $order_nummer : 'MW-' . $m_order_id,
            'tijd'                      => $delivery_time_maatwerk ? $delivery_time_maatwerk : '🕒',
            'eindtijd'                  => $eindtijd_maatwerk ? $eindtijd_maatwerk : '',
            'locatie'                   => $locatie_maatwerk,
            'personen'                  => $personen_maatwerk,
            'order_reference'           => $order_reference_maatwerk,
            'post_status'               => $display_post_status_maatwerk,
            'billing_company'           => $billing_company_maatwerk,
            'maatwerk_voornaam'         => $maatwerk_voornaam,
            'maatwerk_achternaam'       => $maatwerk_achternaam,
            'maatwerk_email'            => $maatwerk_email,
            'maatwerk_telefoonnummer'   => $maatwerk_telefoonnummer,
            'postcode'                  => $postcode,
            'opmerkingen'               => $opmerkingen,
            'important_opmerking'       => $important_opmerking,
            'aantal_medewerkers'        => $aantal_medewerkers,
            'aantal_personen_raw'       => $aantal_personen,
            'optie_geldig_tot'          => $optie_geldig_tot, // Voeg optie_geldig_tot toe aan de agenda array
            'has_note'                  => !empty($opmerkingen) // Voeg 'has_note' vlag toe
        );
    }
    wp_reset_postdata(); // Reset postdata na Maatwerk query in AJAX


    echo generate_agenda_content($agenda, $week_start);
    wp_die();
}

/**
 * AJAX handler om gedetailleerde bestelinformatie op te halen voor de modal.
 */
add_action('wp_ajax_wc_get_order_details_ajax', 'wc_get_order_details_ajax_handler');
add_action('wp_ajax_nopriv_wc_get_order_details_ajax', 'wc_get_order_details_ajax_handler');

function wc_get_order_details_ajax_handler() {
    error_log('wc_get_order_details_ajax_handler function called for order ID: ' . (isset($_POST['order_id']) ? $_POST['order_id'] : 'N/A') . ' and type: ' . (isset($_POST['order_type']) ? $_POST['order_type'] : 'N/A')); // Debug log

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $order_type = isset($_POST['order_type']) ? sanitize_text_field($_POST['order_type']) : ''; // Haal het type op
    $agenda_visibility_setting = isset($_POST['agenda_visibility_setting']) ? sanitize_text_field($_POST['agenda_visibility_setting']) : 'alle_werknemers'; // Haal zichtbaarheidsinstelling op van AJAX

    if ($order_id <= 0) {
        wp_send_json_error('Ongeldig order ID.');
    }

    $output = '';

    if ($order_type === 'woocommerce') {
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('WooCommerce bestelling niet gevonden.');
        }

        // Top Info: Referentie (BQXXX)
        $output .= '<div class="wc-modal-top-info">';
        $order_reference = esc_html(get_post_meta($order_id, 'order_reference', true));
        $order_number = esc_html($order->get_order_number()); // Haal het ordernummer op

        if (!empty($order_reference)) {
            $output .= '' . $order_reference . ' (' . $order_number . ')<br>';
        } else {
            $output .= '(' . $order_number . ')<br>';
        }
        $output .= '</div>';

        // Klantgegevens sectie
        $output .= '<div class="wc-modal-detail-section">';
        $output .= '<p>' . esc_html($order->get_billing_first_name()) . ' ' . esc_html($order->get_billing_last_name()) . '</p>';
        if (!empty($order->get_billing_company())) {
            $output .= '<p>' . esc_html($order->get_billing_company()) . '</p>';
        }
        $output .= '<p><a href="mailto:' . esc_attr($order->get_billing_email()) . '">' . esc_html($order->get_billing_email()) . '</a></p>';
        $output .= '<p><a href="tel:' . esc_attr($order->get_billing_phone()) . '">' . esc_html($order->get_billing_phone()) . '</a></p>';
        $output .= '</div>';

        // Leveringsgegevens sectie (met emojis)
        $output .= '<div class="wc-modal-detail-section">';
        $output .= '<h4>Leveringsgegevens</h4>';

        // Tijd (boven locatie, met emoji)
        $delivery_time = get_post_meta($order_id, 'pi_delivery_time', true);
        $eindtijd      = get_post_meta($order_id, 'order_eindtijd', true);

        $time_display = '';
        if (!empty($delivery_time) && $delivery_time !== '🕒') {
            $time_display .= esc_html($delivery_time);
        }
        if (!empty($eindtijd)) {
            if (!empty($time_display)) {
                $time_display .= ' - ';
            }
            $time_display .= esc_html($eindtijd);
        } else if (empty($time_display) && $delivery_time === '🕒') {
            $time_display = '🕒';
        }

        if (!empty($time_display)) {
            $output .= '<p>🕒 ' . $time_display . '</p>';
        }

        $output .= '<p>📍 ' . esc_html(get_post_meta($order_id, 'order_location', true)) . '</p>';
        $output .= '<p>👥 ' . esc_html(get_post_meta($order_id, 'order_personen', true)) . ' Personen</p>';
        $output .= '</div>';

        // Notities sectie
        $customer_note = $order->get_customer_note();
        if (!empty($customer_note)) {
            $output .= '<div class="wc-modal-detail-section">';
            $output .= '<h4>Notitie</h4>';
            $output .= '<p class="no-title">' . nl2br(esc_html($customer_note)) . '</p>';
            $output .= '</div>';
        }

        // Pakbon weergave is verwijderd zoals gevraagd.

        // Bestelde producten sectie (met nieuwe styling)
        $output .= '<div class="wc-modal-detail-section ordered-products">';
        $output .= '<h4>Bestelde producten</h4>';
        $output .= '<ul>';
        foreach ($order->get_items() as $item_id => $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $output .= '<li><div class="product-name-qty"><span>' . esc_html($product_name) . '</span> <span>x ' . esc_html($quantity) . '</span></div>';

            // Haal en toon product meta data
            $item_meta_data = $item->get_meta_data();
            if ($item_meta_data) {
                $output .= '<ul>';
                foreach ($item_meta_data as $meta) {
                    if (strpos($meta->key, '_') !== 0) { // Sluit verborgen meta keys uit
                        $output .= '<li><strong>' . esc_html(ucfirst(str_replace('_', ' ', $meta->key))) . ':</strong> ' . esc_html($meta->value) . '</li>';
                    }
                }
                $output .= '</ul>';
            }
            $output .= '</li>'; // Belangrijke fix: sluit de <li> tag
        }
        $output .= '</ul>';
        $output .= '</div>';

    } elseif ($order_type === 'maatwerk') {
        // Haal maatwerk order post op
        $m_order_post = get_post($order_id);

        if (!$m_order_post || $m_order_post->post_type !== 'maatwerk_bestelling') {
            wp_send_json_error('Maatwerk bestelling niet gevonden.');
        }

        // Haal alle maatwerk meta-data op
        $order_nummer            = get_post_meta($order_id, 'order_nummer', true);
        $maatwerk_voornaam       = get_post_meta($order_id, 'maatwerk_voornaam', true);
        $maatwerk_achternaam     = get_post_meta($order_id, 'maatwerk_achternaam', true);
        $maatwerk_email          = get_post_meta($order_id, 'maatwerk_email', true);
        $maatwerk_telefoonnummer = get_post_meta($order_id, 'maatwerk_telefoonnummer', true);
        $bedrijfsnaam            = get_post_meta($order_id, 'bedrijfsnaam', true);
        $straat_huisnummer       = get_post_meta($order_id, 'straat_huisnummer', true);
        $postcode                = get_post_meta($order_id, 'postcode', true);
        $plaats                  = get_post_meta($order_id, 'plaats', true);
        $referentie              = get_post_meta($order_id, 'referentie', true);
        $datum                   = get_post_meta($order_id, 'datum', true);
        $start_tijd              = get_post_meta($order_id, 'start_tijd', true);
        $eind_tijd               = get_post_meta($order_id, 'eind_tijd', true);
        $aantal_medewerkers      = get_post_meta($order_id, 'aantal_medewerkers', true);
        $aantal_personen         = get_post_meta($order_id, 'aantal_personen', true);
        // Gebruik de correcte meta key voor opmerkingen
        $opmerkingen             = get_post_meta($order_id, '_maatwerk_bestelling_opmerkingen', true); // Aangepast naar de correcte meta key
        $pdf_uploads             = get_post_meta($order_id, '_pdf_attachments', true); // Haal PDF uploads op
        $order_status_maatwerk   = get_post_meta($order_id, 'order_status', true); // Haal de daadwerkelijke maatwerk order status op
        $optie_geldig_tot        = get_post_meta($order_id, 'optie_geldig_tot', true); // Haal de optie geldig tot datum op

        // Top Info: Referentie (Maatwerk)
        $output .= '<div class="wc-modal-top-info">';
        $maatwerk_display_order_number = !empty($order_nummer) ? $order_nummer : 'MW-' . $order_id;
        if (!empty($referentie)) {
            $output .= ' ' . esc_html($referentie) . ' (' . esc_html($maatwerk_display_order_number) . ')<br>';
        } else {
            $output .= "Maatwerk Bestelling: " . esc_html($maatwerk_display_order_number) . "<br>";
        }

        // Toon "In optie tot" ook in de modal
        if ($order_status_maatwerk === 'in-optie' && !empty($optie_geldig_tot)) {
            $option_date_parsed = null;
            if (DateTime::createFromFormat('Y-m-d', $optie_geldig_tot)) {
                $option_date_parsed = DateTime::createFromFormat('Y-m-d', $optie_geldig_tot);
            } elseif (DateTime::createFromFormat('d-m-Y', $optie_geldig_tot)) {
                $option_date_parsed = DateTime::createFromFormat('d-m-Y', $optie_geldig_tot);
            } elseif (DateTime::createFromFormat('m/d/Y', $optie_geldig_tot)) {
                $option_date_parsed = DateTime::createFromFormat('m/d/Y', $optie_geldig_tot);
            }

            if ($option_date_parsed) {
                $today = new DateTime();
                $interval = $today->diff($option_date_parsed);
                $days_diff = (int)$interval->format('%r%a');

                $option_date_formatted = $option_date_parsed->format('j F Y');
                $output .= '<span>In optie t/m: ' . $option_date_formatted;
                if ($days_diff > 0) {
                    $output .= ' (Nog ' . $days_diff . ' dag' . ($days_diff > 1 ? 'en' : '') . ')';
                } elseif ($days_diff === 0) {
                    $output .= ' (Laatste dag!)';
                } else {
                    $days_elapsed = abs($days_diff);
                    $output .= ' (' . $days_elapsed . ' dag' . ($days_elapsed > 1 ? 'en' : '') . ' verlopen)';
                }
                $output .= '</span>';
            }
        }
        $output .= '</div>'; // Sluit wc-modal-top-info

        // Bewerkknop voor Maatwerk bestellingen (alleen voor admins)
        // Haal de huidige gebruiker op om rollen te controleren
        $current_user = wp_get_current_user();
        $user_roles = (array) $current_user->roles;
        if (in_array('administrator', $user_roles)) { // Gewijzigd naar expliciete 'administrator' rolcontrole
            $edit_url = 'https://banquetingportaal.nl/alg/maatwerk/?maatwerk_edit=' . $order_id;
            $output .= '<div style="text-align: right; margin-bottom: 15px;">';
            // Gebruik window.open() om een nieuw venster gecentreerd te openen
            $output .= '<a href="#" onclick="
                var width = 800;
                var height = 800; // Aangepast naar 800px hoogte
                var left = (screen.width / 2) - (width / 2);
                var top = (screen.height / 2) - (height / 2);
                window.open(\'' . esc_url($edit_url) . '\', \'_blank\', \'width=\' + width + \',height=\' + height + \',top=\' + top + \',left=\' + left + \',resizable=yes,scrollbars=yes\');
                return false;
            " class="wc-edit-button" style="background-color: #4CAF50; color: #fff; padding: 8px 12px; border-radius: 5px; text-decoration: none; font-weight: bold;">Bewerken</a>';
            $output .= '</div>';
        }

        // Klantgegevens
        $output .= '<div class="wc-modal-detail-section">';
        $output .= '<h4>Klantgegevens</h4>'; // Voeg titel toe voor consistentie
        $output .= '<p>' . esc_html($maatwerk_voornaam) . ' ' . esc_html($maatwerk_achternaam) . '</p>';
        if (!empty($bedrijfsnaam)) {
            $output .= '<p>' . esc_html($bedrijfsnaam) . '</p>';
        }
        $output .= '<p><a href="mailto:' . esc_attr($maatwerk_email) . '">' . esc_html($maatwerk_email) . '</a></p>';
        $output .= '<p><a href="tel:' . esc_attr($maatwerk_telefoonnummer) . '">' . esc_html($maatwerk_telefoonnummer) . '</a></p>';
        $output .= '</div>';

        // Leveringsgegevens
        $output .= '<div class="wc-modal-detail-section">';
        $output .= '<h4>Leveringsgegevens</h4>';

        $time_display_maatwerk = '';
        if (!empty($start_tijd)) {
            $time_display_maatwerk .= esc_html($start_tijd);
        }
        if (!empty($eind_tijd)) {
            if (!empty($time_display_maatwerk)) {
                $time_display_maatwerk .= ' - ';
            }
            $time_display_maatwerk .= esc_html($eind_tijd);
        } else if (empty($time_display_maatwerk)) { // Fallback als er geen specifieke tijd is
            $time_display_maatwerk = '🕒';
        }

        if (!empty($time_display_maatwerk)) {
            $output .= '<p>🕒 ' . $time_display_maatwerk . '</p>';
        }

        $full_address = '';
        if (!empty($straat_huisnummer)) {
            $full_address .= esc_html($straat_huisnummer);
        }
        if (!empty($postcode)) {
            if (!empty($full_address)) $full_address .= ', ';
            $full_address .= esc_html($postcode);
        }
        if (!empty($plaats)) {
            if (!empty($full_address)) $full_address .= ', ';
            $full_address .= esc_html($plaats);
        }
        if (!empty($full_address)) {
            $output .= '<p>📍 ' . $full_address . '</p>';
        } else {
            $output .= '<p>📍 Locatie onbekend</p>';
        }

		// Personen/medewerkers
		if (!empty($aantal_personen)) {
  		  $output .= '<p>👥 <b>' . esc_html($aantal_personen) . '</b> Personen</p>';
		}

		// Medewerkers: alleen tonen bij >0, met enkelvoud/meervoud
		if (!empty($aantal_medewerkers)) {
   		 $count = intval($aantal_medewerkers);
    		$label = $count === 1 ? 'Medewerker' : 'Medewerkers';
    		$output .= '<p>👷 <b>' . esc_html($count) . '</b> ' . esc_html($label) . '</p>';
		}

        $important_opmerking = get_post_meta($order_id, 'important_opmerking', true);
        if (!empty($important_opmerking)) {
            $output .= '<p><strong>✏️</strong> ' . esc_html($important_opmerking) . '</p>';
        }


        // *** Einde toevoeging ***
		
        $output .= '</div>';

        // Opmerkingen
        if (!empty($opmerkingen)) {
            $output .= '<div class="wc-modal-detail-section">';
            $output .= '<h4>Opmerkingen</h4>';
            $output .= '<p class="no-title">' . nl2br(esc_html($opmerkingen)) . '</p>';
            $output .= '</div>';
        }

        // PDF links (als je die meta data hebt) - Toon alleen als agenda_visibility_setting 'alle_werknemers' is OF als huidige gebruiker admin is
        $current_user_is_admin = current_user_can('manage_options');

        if (!empty($pdf_uploads) && is_array($pdf_uploads)) {
            if ($agenda_visibility_setting === 'alle_werknemers' || $current_user_is_admin) {
                $output .= '<div class="wc-modal-detail-section ordered-products">'; // Hergebruik ordered-products voor styling
                $output .= '<h4>Bijgevoegde PDF\'s</h4>';
                $output .= '<ul>';
                foreach ($pdf_uploads as $pdf_file) {
                    // Ervan uitgaande dat elk item in $pdf_uploads een array is met 'url'
                    if (is_array($pdf_file) && isset($pdf_file['url'])) {
                        $filename = isset($pdf_file['filename']) ? esc_html($pdf_file['filename']) : 'Download PDF';
                        $output .= '<li><a href="' . esc_url($pdf_file['url']) . '" target="_blank">' . $filename . '</a></li>';
                    } else {
                        // Fallback voor als het alleen een URL is (bijv. een simpele string)
                        $output .= '<li><a href="' . esc_url($pdf_file) . '" target="_blank">Download PDF</a></li>';
                    }
                }
                $output .= '</ul>';
                $output .= '</div>';
            } else {
                // Als zichtbaarheid 'interne_medewerkers' is en gebruiker geen admin, toon PDF sectie niet
                error_log('DEBUG: PDF section hidden for maatwerk order ' . $order_id . ' due to visibility setting and non-admin user.');
            }
        }

    } else {
        wp_send_json_error('Onbekend order type.');
    }

    wp_send_json_success($output); // Gebruik wp_send_json_success voor consistente AJAX-respons
}

/**
 * AJAX handler om een dagelijks productoverzicht te genereren.
 */
add_action('wp_ajax_wc_get_daily_product_summary_ajax', 'wc_get_daily_product_summary_handler');
add_action('wp_ajax_nopriv_wc_get_daily_product_summary_ajax', 'wc_get_daily_product_summary_handler');

function wc_get_daily_product_summary_handler() {
    error_log('wc_get_daily_product_summary_handler function called for day: ' . (isset($_POST['day_date']) ? $_POST['day_date'] : 'N/A'));

    $day_date_str = isset($_POST['day_date']) ? sanitize_text_field($_POST['day_date']) : '';

    if (empty($day_date_str)) {
        wp_send_json_error('Geen geldige datum opgegeven voor dagoverzicht.');
    }

    // Zorg ervoor dat de datum in het juiste formaat is voor de meta_query
    $day_date_query_format = date('Y-m-d', strtotime($day_date_str));
    $day_date_display_format = date_i18n('l j F Y', strtotime($day_date_str));

    $product_summary_by_category = []; // Nieuwe structuur: category => [product => quantity]

    $args_wc = array(
        'post_type'      => 'shop_order',
        'posts_per_page' => -1,
        // Sluit 'wc-cancelled' uit van de statussen voor het dagoverzicht
        'post_status'    => array_diff(array_keys(wc_get_order_statuses()), ['wc-cancelled']),
        'meta_query'     => array(
            array(
                'key'     => 'pi_system_delivery_date',
                'value'   => $day_date_query_format,
                'compare' => '=',
                'type'    => 'DATE'
            )
        )
    );

    $wc_orders = get_posts($args_wc);

    foreach ($wc_orders as $order_post) {
        $order = wc_get_order($order_post->ID);
        // Verwerk alleen als de order niet geannuleerd is
        if (!$order || $order->get_status() === 'cancelled') {
            continue;
        }

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();

            $category_name = 'Overig'; // Standaard categorie
            if (stripos($product_name, 'Lunch') !== false) {
                $category_name = '<u>Lunch opties</u>'; // Nieuwe specifieke categorie voor lunch
            } else if ($product) {
                $categories = $product->get_category_ids(); // Krijg een array van categorie ID's
                if (!empty($categories)) {
                    $first_category_id = $categories[0];
                    $term = get_term($first_category_id, 'product_cat');
                    if ($term && !is_wp_error($term)) {
                        $category_name = $term->name;
                    }
                }
            }

            // Sla op in de geneste array
            if (!isset($product_summary_by_category[$category_name])) {
                $product_summary_by_category[$category_name] = [];
            }
            if (isset($product_summary_by_category[$category_name][$product_name])) {
                $product_summary_by_category[$category_name][$product_name] += $quantity;
            } else {
                $product_summary_by_category[$category_name][$product_name] = $quantity;
            }
        }
    }
    wp_reset_postdata(); // Reset postdata na WooCommerce query

    $output = '<div class="wc-modal-top-info">';
    $output .= '' . esc_html($day_date_display_format);
    $output .= '</div>';

    if (!empty($product_summary_by_category)) {
        $output .= '<div class="wc-modal-detail-section ordered-products">';
        $output .= '<ul>';

        // Scheid 'Lunch opties' producten
        $lunch_products_display = [];
        if (isset($product_summary_by_category['Lunch opties'])) {
            $lunch_products_raw = $product_summary_by_category['Lunch opties'];
            ksort($lunch_products_raw); // Sorteer producten binnen Lunch alfabetisch
            foreach ($lunch_products_raw as $product_name => $total_quantity) {
                $lunch_products_display[] = ['name' => esc_html($product_name), 'qty' => esc_html($total_quantity)];
            }
            unset($product_summary_by_category['Lunch opties']);
        }

        // Sorteer alle andere categorieën alfabetisch op categorienaam
        ksort($product_summary_by_category);

        // Bereid en sorteer andere producten voor weergave
        $other_products_display = [];
        foreach ($product_summary_by_category as $category_name => $products_in_category) {
            ksort($products_in_category); // Sorteer producten binnen elke andere categorie alfabetisch
            foreach ($products_in_category as $product_name => $total_quantity) {
                $other_products_display[] = ['name' => esc_html($product_name), 'qty' => esc_html($total_quantity)];
            }
        }

        // Toon eerst andere producten
        foreach ($other_products_display as $product_data) {
            $output .= '<li><div class="product-name-qty"><span>' . $product_data['name'] . '</span> <span>x ' . $product_data['qty'] . '</span></div></li>';
        }

        // Toon daarna de "Lunch opties" producten, voorafgegaan door het label als er producten zijn
        if (!empty($lunch_products_display)) {
            $output .= '<li><div class="product-name-qty" style="margin-top: 15px;"><strong>Lunch opties</strong></div></li>'; // Categorielabel voor Lunch
            foreach ($lunch_products_display as $product_data) {
                $output .= '<li><div class="product-name-qty"><span>' . $product_data['name'] . '</span> <span>x ' . $product_data['qty'] . '</span></div></li>';
            }
        }

        $output .= '</ul>';
        $output .= '</div>';
    } else {
        $output .= '<div class="wc-modal-detail-section">';
        $output .= '<p style="text-align: center;">Geen banqueting orders vandaag</p>';
        $output .= '</div>';
    }

    wp_send_json_success($output);
}
