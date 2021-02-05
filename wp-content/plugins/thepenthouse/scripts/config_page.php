<?php
add_action( 'admin_menu', 'thp_settings' );


function thp_settings()
{
    add_menu_page( 'The Penthouse Settings', 
    "TPH Settings", "manage_options", 
    sanitize_key('The Penthouse Settings'), 'thp_settings_content', 
    'dashicons-admin-multisite', 80 );
    
}


function thp_settings_content()
{
   ?>
    <h2> Welcome to The Penthouse admin page</h2>
    <p> Use this action for synchronize reservations with Guesty platform</p>
    <form method="POST" action="<?php echo admin_url( 'admin.php' ); ?>">
    <input type="hidden" name="action" value="thphsynccalendar" />
    <input type="submit" value="Sync now">
    </form>
   <?php
    
}

add_action( 'admin_action_thphsynccalendar', 'thphsynccalendar' );
function thphsynccalendar()
{
    $guesty = new Guesty();
    $reservationsCounter = $reservationsAvailable = 0;

    while ($reservationsCounter == 0 || $reservationsCounter < $reservationsAvailable) {
        $response = $guesty->reservations([
        'fields' => 'listingId guestsCount checkInDateLocalized checkOutDateLocalized status guest.firstName guest.lastName guest.email guest.phone customFields', 
        'filter' => '[{"field":"status", "operator":"$in", "value":["confirmed"]}', 
        'limit' => 50,
        'skip' => $reservationsCounter
        ]);
        if ($response['status'] == 200 && count($response['result']['results']) > 0) {
            $reservations = $response['result']['results'];
            $reservationsAvailable = $response['result']['count'];
            $reservationsCounter +=  $response['result']['limit'];
            
            foreach ($reservations as $reservation) {
                $calendar = new Calendar();
                $calendar->syncCalendar($reservation);
                echo $reservation['_id']." done </br>";
            }
        } else {
            break;
        }     
    }

    exit();
}

// add_action( 'admin_action_thphsynccalendar', 'thphsynccalendar' );
// function thphsynccalendar()
// {
//     $guesty = new Guesty();
//     $reservationsCounter = $reservationsAvailable = 0;
//     $fp = fopen('fichero.csv', 'w');

//    while ($reservationsCounter == 0 || $reservationsCounter < $reservationsAvailable) {
// $ids = ['600b643fd05d2e002fb7d1f7','600c1434a1fa8000304b257a','600c149a0dffd6002d2d73e0','600c1663a6373e003073868c','600c17060dffd6002d2d7eda','600c3535a4b833002c0e40d5','600c4088207b3c003079d925','600c5777207b3c00307bec7a','600c5a5561d975002f41faf9','600c5fc1a6373e003075c766','600cee499a9ccf002f8eabb9','600d53448fd0af003075460f','600d5e26e7aac7002e5f2864','600d9a8cc5c0a6002e910e40','600da637920578002f744f0a','600db3bc7c0c9f002d1a455f','600dc32079f653002cd88f71','600e02e2920578002f7c4843','600e6a68920578002f86362b','600ea46ae7aac7002e67112e','600ea6d1b68f71002eb7a9a6','600ea712920578002f8badc0','600eb600da4e84002fbb3d77','600f08a57c0c9f002d234a0c','600f0e7879f653002ce1f997','600f162d920578002f93b6b0','600f2a90b68f71002eba7597','600f2e345c675f002f2f0f78','600f63e3b68f71002ebbd910','600fe33f5c675f002f443d8a','600fe4965c675f002f446148','600ff59b3d8658002c6b0ddb','600ffc5df128d1002f6b5f98','60100450e8f6aa002fb7a8bb','6010187c5334ab0032937165','6010456daa331f0030956ae8','60104ccb2466c4002e1ed2d0','60104f5daa331f003095d293','60105b8b7702db002db240a9','601065212466c4002e20e02b','6010804571a812002ea65d60','6010ad0fdb9886003075eee7','6010d7be1d67e2002f023fb9','60112ddbf065f1002e4bb975','601132f2823a7f00304c7334','601146f0652df3002f0e64f1','60117c70d1a8860030697b36','60118741bb361c0030154162','60118a858e14c2002ee4441b','6011cf389a4f44002da4a3c5','6011f9f9ca838d002e6f32f8','6011ff039a4f44002da98269','60127b45ac20120030d80820','6012a0542726670030979155','6012b3f177c811002f9eade4','6012b45ffc4538002f9fe2d6','6012c71c272667003098b418','6012d4ab9a4f44002dbfa1ea','6012dc879a4f44002dc05dd6','6013575a9a4f44002dcd1d09','6013f2039a4f44002ddd417b','6013f9e9fc4538002fa5a215','601411b4e9cb99002e62c43a','601429d31ab32f0031c49bbb','60143786e9cb99002e643bc9','601438a3e9cb99002e644711','601439e00d6c7400308f7c44','60145a48fbac510030b5bca3','60146af69a4f44002de8d2c9','601477199a4f44002de9df75','60153ed7e4c0e5002d718ab0','601554b60d6c74003096e72b','6015a189fbac510030d3e521','60163e8044c771002ef2b773','6016b694836a44002d8ef397','6016b91437655c002f14397e','6016c25119adce002d872bf0','6016d54140feec00310d964e','6016d8a8add627002f313316','601754b62bbf3b002f052853','6017c58394d0ce002d6e7613','6017dd2a7e7e1b0030c484c8','601810fed547d5002ef46d2e','60181a68a550c4002fbdbf6d','60181b5af30d5d0030c9850d','60181d778b42e4002f48a2ca','6018291fb0a3a9002ed15516','60182921da22b6002fdca581','60182d6cf30d5d0030ca39d9','60182e29a550c4002fbead99','6019043a7905f8002e5f859e','60193e17897a2d002ecf23d1','60194c629f8f830033ec5e2c','6019546cee1794002eac107c','60195b0c3806fd002d30bf6d','6019615212a56e002dbdb81a','60197ca0ee1794002ead946b','6019ab5925e24e002ddb435b','6019c0edbae253002eda8694','6019c416c13714002fb1723b','601a773aefdb540030bf2cfd','601a86043524e100304f7a71','601a9aa23af46a002ee5f9d5','601a9d7004eba6002fdd3910','601aab486ff376002d4e35ae','601ab6045f3578002e5bccc6','601ad35d2a6a9d002f9649da','601b03db2b80df002c2a2d98','601b07e5ead9b8002e2eb722','601b1bda2b80df002c2c850c','601c2877e22e67002ebc7003','601c542e66880b002f307280'];
// $response = $guesty->reservations([
//         //    'fields' => 'listingId guestsCount checkInDateLocalized checkOutDateLocalized status guest.firstName guest.lastName guest.email guest.phone customFields money', 
//            'fields' => 'listing.title guestsCount checkInDateLocalized checkOutDateLocalized status guest.firstName guest.lastName guest.email guest.phone money.hostPayout canceledAt', 
//         //    'filter' => '[{"field":"status", "operator":"$in", "value":["canceled"]}', 
//            'limit' => 50,
//            'viewId'=> '601d33aae81d90002fde078f',
//            'skip' => $reservationsCounter
//         ]);
//         if ($response['status'] == 200 && count($response['result']['results']) > 0) {
//             $reservations = $response['result']['results'];
//             $reservationsAvailable = $response['result']['count'];
//             $reservationsCounter +=  $response['result']['limit'];

//             foreach ($reservations as $campos) {
//                 unset($campos['integration']);
//                 unset($campos['accountId']);
//                 unset($campos['guestId']);
//                 unset($campos['listingId']);
//                 $campos['money'] = $campos['money']['hostPayout'];
//                 $campos['email'] = $campos['guest']['email'] ?? '';
//                 $campos['phone'] = $campos['guest']['phone'] ?? '';
//                 $campos['guest'] = $campos['guest']['firstName']." ". $campos['guest']['lastName'];
//                 $campos['listing'] = $campos['listing']['title'];
//                 ksort($campos);

//                 if (in_array($campos['_id'], $ids))
//                     fputcsv($fp, $campos);
//             }
            
//         //     foreach ($reservations as $reservation) {
//         //         $calendar = new Calendar();
//         //         $calendar->syncCalendar($reservation);
//         //         echo $reservation['_id']." done </br>";
//             // }
//         } else {
//             break;
//         }     
//     }
//     fclose($fp);

//     exit();
// }


