<?php
/*
Plugin Name: EVAA Load Calculator
Plugin URI: https://github.com/Gabtoof/evaa-load-calculator
Description: A plugin to calculate the electrical load for adding an EV charger.
Version: 1.0.73
Author: Andrew Baituk
*/

// using data from https://www.edmonton.ca/sites/default/files/public-files/assets/PDF/Electrical_Inspection_Load_Calculation.pdf?cb=1625176204
// actually, https://iaeimagazine.org/2013/mayjune-2013/residential-load-calculations/ has even better/easier to understand data

/* Todo:
[*] test AC changes
[*] Fix show of watts
[*] figure out 'additional loads' like electric dryer, electric water heater, 
[*] user larger of heat/ac
[*] get sources for: hot tub
[*] in floor heat
[*] gas water heater (likely 0 for calc)
[*] tankless water heater - removed as this can be gas or electric and thus covered under those
[*] gas dryer (likely 0)
[*] add heat pump dryer
[*] gas stove
[*] remove dishwasher
[*] test if m2 used, conversion (seems ok if sq ft used)
[*] fix hot tub/in floor heat being added even if no
[ ] handle plugin updates - maybe broke it trying to compare versions

[*] expand info on EV charger - suggest lower charging when needed
[*] suggest load balancer/loadmiser when applicable
[ ] clean up wording around EV charger
[ ] get someone to double check logic
[ ] make pretty
[*] remove 'confusing' result text on first load
[*] add above/below ground electrical service

[*] FIX: double RT output after stove
[*] FIX: if AC defaults to 3300 for 181 size house, and manual heat is 3000 it SHOULD take AC but its taking HEAT
[*] FIX: in floor heat output not displaying incremental value
[*] expand Hot Tub to include pool/sauna/etc
[ ] show default values to be user where doable via 'background field text when empty'
[*] Clarify output: w/ prefix "ADD: " for each item
[*] FIX: auto adjustments should ensure stove is > some value (1 for now)
[ ] ADD: Total additional loads over 1500W (apply 25% calc)
[*] Address Tankless Water Heaters
[ ] compare with https://www.blackboxelectrical.com/pages/electrical-service-load-calculator-for-single-residences-canada

// Final website items
[ ] mention for convience we are using various estimates AND simplifying things slightly (ie ignoring basements) - best data by entering own values
        * assuming AC and electric Heat set to not run same time
[ ] add disclaimers/etc

// Maybe
[ ] show default values to be user

// Version 2
[ ] explore allowing EV selection and daily commute

// Potential icons
[ ] fix water heater icons
[ ] make icons more consistant background or something
[ ] ensure icons look 'selected'
[ ] hover over glow on icons


*/
 



// Enqueue the JS script
function evaa_load_calculator_scripts() {
    // Use the WordPress built-in script versioning for cache-busting
    $version = wp_get_theme()->get('Version');

    // wp_enqueue_script(
    //     'evaa-calculator',
    //     plugin_dir_url(__FILE__) . 'evaa-load-calculator.js',
    //     array('jquery'), // Dependencies
    //    // $version, // Version number for cache busting
    //     true // Load in footer
    // );
}
add_action('wp_enqueue_scripts', 'evaa_load_calculator_scripts');


//short code
function load_calculator_form_shortcode() {
    ob_start(); // Start output buffering






session_start(); // Ensure session_start() is called at the beginning



// Initialize variables with default values
$home_size_unit = "sqft"; // Replace 'default_unit' with whatever default value you deem appropriate
$home_size = 0; // Default to 0, or any other appropriate value
$ac_load = 0;
$heating_load = 0;
$output = ""; // Initialize output buffer
$clothes_dryer_w = 0;
$water_heater_w = 0;
$stove_w = 0;
$formSubmitted = !empty($_POST); // Check if any POST data exists
// Example of default value: $hottub_default_wattage: 12000
// if used, link it to value used in calcs later with: $hottub_wattage = $hottub_default_wattage;
// Use <?php echo $hottub_default_wattage; ? > (no space between ? and > ) to use the default value



// Check if the keys exist in the $_POST data and assign them
if (isset($_POST['home_size_unit'])) {
    $home_size_unit = $_POST['home_size_unit'];
}

if (isset($_POST['home_size'])) {
    $home_size = intval($_POST['home_size']); // Convert to integer for safety
}


$total_load = 0;
$message = "";


// Define the array of EV chargers
// https://evadept.com/calc/ev-charging-time-calculator
$ev_chargers = [
    ["amperage" => 12, "wattage" => 2800, "kW" => 2.8,  "kmPerHour" => 19, "fullChargeTime" => "approx 21h"],
    ["amperage" => 16, "wattage" => 3800, "kW" => 3.8,  "kmPerHour" => 24, "fullChargeTime" => "approx 16.25h"],
    ["amperage" => 24, "wattage" => 5700, "kW" => 5.7,  "kmPerHour" => 37, "fullChargeTime" => "approx 10.5h"],
    ["amperage" => 32, "wattage" => 7600, "kW" => 7.6,  "kmPerHour" => 50, "fullChargeTime" => "approx 8h"],
    ["amperage" => 40, "wattage" => 9600, "kW" => 9.6,  "kmPerHour" => 61, "fullChargeTime" => "approx 6.5h"],
    ["amperage" => 48, "wattage" => 11500, "kW" => 11.5, "kmPerHour" => 74, "fullChargeTime" => "approx 5.25h"]
];

// If the form has been submitted
if(isset($_POST['submit'])) {






    
    // Service Panel Capacity in Amps
$panel_capacity_amps = intval($_POST['panel_capacity_amps']);

// Convert to Watts (assuming 240V)
$panel_capacity = $panel_capacity_amps * 240;

//echo "Original Home Size: " . $home_size . " " . $home_size_unit . "<br>";
//$output .= "Original Home Size: " . $home_size . " " . $home_size_unit . "<br>";

// Convert home size to m² and sqft
$home_size_m2 = ($home_size_unit == "sqft") ? $home_size * 0.092903 : $home_size;
$home_size_sqft = ($home_size_unit == "m2") ? $home_size * 10.764 : $home_size;

$output .= "Home Size: " . $home_size_m2 . " m2 / " . $home_size_sqft . "sq ft<br>";
//$output .= "Converted Home Size in sqft: " . $home_size_sqft . " sqft<br>";

// Living Area load calculation based on m²
// Accepting that this doesn't factor in 75% for basements. That adds complexity to user and this will give us only a slightly more 'conservative' result (more likely to say EV charger may not 'fit')
if($home_size_m2 <= 90) {
$total_load += 5000;
} else {
$total_load += 5000 + (1000 * ceil(($home_size_m2 - 90)/90));
}
  
$output .= "Base Living Area Load (first 90 m2): 5000 W<br>";
$output .= "Additional Living Area Load (1000 W each additional 90 m2): " . ($total_load - 5000) . " W<br>";
$output .= "Total Living Area Load: " . $total_load . " W<br>";

// Heating
// https://iaeimagazine.org/2013/mayjune-2013/residential-load-calculations/
// Note: for simplicity (and because it means we are more likely to suggest NOT ENOUGH capcaity for charger) we are ignoreing the 75% aspect of: For electric space-heating systems consisting of electric thermal storage heating, duct heater, or an electric furnace, the connected heating load is calculated at 100% of the equipment ratings. Where the electric heating installation is provided with automatic thermostatic control devices in each room or heated area, the electric space-heating load is 100% of the first 10 kW of connected heating load plus the balance of the connected heating load at a demand factor of 75%.
$heating_type = $_POST['heating'];
switch($heating_type) {
    case "gas":  
        $heating_load = 0; // No additional load for gas heating
        break;
    case "electric":
        if($home_size_sqft <= 1500) {
            $heating_load = 18000;
        } elseif($home_size_sqft > 1500 && $home_size_sqft <= 3000) {
            $heating_load = 40000;
        } else {
            $heating_load = 54000;
        }
        break;
    case "air_heat_pump": //https://sourceheatpump.com/how-much-electricity-air-source-heat-pump-uses/
        if($home_size_sqft <= 1500) {
            $heating_load = 13200;
        } elseif($home_size_sqft > 1500 && $home_size_sqft <= 3000) {
            $heating_load = 22000;
        } else {
            $heating_load = 26300;
        }
        break;
    case "heating_wattage":
        $heating_load = intval($_POST['user_provided_heating_wattage']);
        break;
}


// AC calculation
// https://www.thisoldhouse.com/heating-cooling/reviews/what-size-air-conditioner-do-i-need
// https://www.electricalcalculators.org/air-conditioner-power-consumption-calculator/#:~:text=Answer%3A%202%20Ton%20ac%20%3D%202400%20watt%20%3D,of%20%240.2%2FkWh%20%3D%207.2%20kWh%20%2A%240.2%2FkWh%20%3D%20%241.44
// 1500sqft 2ton 2400w, 2200 2.75t 3300w, 3000 3.5t 4200w
if(isset($_POST['ac']) && $_POST['ac'] === 'yes') {
    // If AC wattage is provided by user, use that; otherwise, use default
    if (isset($_POST['user_provided_ac_wattage']) && !empty($_POST['user_provided_ac_wattage'])) {
        $ac_load = intval($_POST['user_provided_ac_wattage']);
    } else {
        // Determine AC load based solely on home size sqft
        if ($home_size_sqft <= 1500) {
            $ac_load = 2400; // for homes up to 1500 sqft
        } elseif ($home_size_sqft > 1500 && $home_size_sqft <= 2200) {
            $ac_load = 3300; // for homes between 1501 and 2200 sqft
        } elseif ($home_size_sqft > 2200 && $home_size_sqft <= 3000) {
            $ac_load = 3300; // Adjusted to match the provided scales
        } else {
            $ac_load = 4200; // for homes larger than 3000 sqft
        }
    }
}

// Decide which load to add based on which is larger
if ($ac_load > $heating_load) {
    $total_load += $ac_load;
    // Debugging output
    $output .= "AC Load: {$ac_load} W | Heating Load: {$heating_load} W<br>Using larger of AC or Heating load: AC <br>";
} else {
    $total_load += $heating_load;
    // Debugging output
    $output .= "AC Load: {$ac_load} W | Heating Load: {$heating_load} W<br>Using larger of AC or Heating load: Heating <br>";
}

$output .= "Running total: " . $total_load . " W<br>";


// Stove
$stove_type = $_POST['stove'];

switch($stove_type) {
    case "electric":
        // https://iaeimagazine.org/2013/mayjune-2013/residential-load-calculations/
        // https://www.lg.com/ca_en/cooking-appliances/ranges/lsil6336f/
        // $total_load += 12000; // 80% of a 40A circuit
        $total_load += 6000; // 6kW allowance up to 12kW actual per https://iaeimagazine.org/2013/mayjune-2013/residential-load-calculations/
        $stove_w = 6000;
        break;
    case "gas":
        
        $total_load += 0; // covered in base load - less than 1500W
        break;

    case "stove_wattage":
        if (isset($_POST['user_provided_stove_wattage']) && !empty($_POST['user_provided_stove_wattage'])) {
            $total_load += intval($_POST['user_provided_stove_wattage']);
            $stove_w = intval($_POST['user_provided_stove_wattage']);
        }
        break;
    // Add cases for other types if necessary
}
// Flag to indicate if the electric stove is selected
// Check if the stove type is either "electric" or the user has chosen to provide a custom wattage for an electric stove
// $isElectricStoveSelected = ($stove_type === "electric" || $stove_type === "stove_wattage");
// set $isElectricStoveSelected based on wattage being equal to or greater than 1500W. changed to this so if user sets custom but doesn't enter value OR enters small value, we don't reduce other loads
if(isset($stove_w) && $stove_w >= 1500) {
    $isElectricStoveSelected = true;
} else {
    $isElectricStoveSelected = false;
}

// Output the stove type
$output .= "Stove Type: " . $stove_type . "<br>";
$output .= "ADD: Stove: " . $stove_w . " W<br>";

// Output whether an electric stove is selected, considering both "electric" and "stove_wattage" as valid conditions
// $output .= "Is Electric Stove Selected: " . ($isElectricStoveSelected ? "Yes" : "No") . "<br>";

$output .= "Running total: " . $total_load . " W<br>";


// Water Heater
$water_heater_type = $_POST['water_heater'];

switch ($water_heater_type) {
    case "electric":
        // if electric stove, only use 25%
        // https://solvitnow.com/blog/what-size-breaker-do-i-need-for-my-water-heater/ - depreciated
        // https://www.archute.com/electricity-water-heater-use/ - using this now
        $electricWaterHeaterWattage = 4500;
        $loadToAdd = $isElectricStoveSelected ? ($electricWaterHeaterWattage * 0.25) : $electricWaterHeaterWattage; // Default wattage for electric water heater
        $total_load += $loadToAdd; 
        $water_heater_w = $loadToAdd;
        // Debugging Echoes
        $output .= "Water Heater Type: Electric Tank<br>";
        if (!$isElectricStoveSelected) {
            $output .= "ADD: Water Heater: $electricWaterHeaterWattage W<br>";
        } else {
            $output .= "Water Heater: $electricWaterHeaterWattage W (before adjustment)<br>";
            $output .= "ADD (after auto adjustment due to electric stove): " . ($electricWaterHeaterWattage * 0.25) . " W<br>";
        }
        $output .= "Running total: " . $total_load . " W<br>";
        break;
    case "tankless_water_heater":
        // Determine the wattage based on home size for a tankless water heater
        // https://learnmetrics.com/how-much-electricity-does-a-tankless-water-heater-use/
        // https://cleancoolwater.com/how-much-electricity-does-a-tankless-water-heater-use/ (manual estimated combination of both)
        if ($home_size_sqft <= 1500) {
            $loadToAdd = 12000; // Default wattage for smaller homes
        } elseif ($home_size_sqft > 1500 && $home_size_sqft <= 3000) {
            $loadToAdd = 18000; // Increased wattage for medium-sized homes
        } else {
            $loadToAdd = 23000; // Maximum wattage for larger homes
        }
        // Since the tankless water heater operates at a 100% load factor,
        // there is no adjustment based on the electric stove.
        $total_load += $loadToAdd;
        $water_heater_w = $loadToAdd;
        // Detailed Debugging Echoes for tankless water heater
        $output .= "Water Heater Type: Electric Tankless<br>";
        $output .= "ADD: Electric Tankless Water Heater: " . $loadToAdd . " W<br>";
        $output .= "Running total: " . $total_load . " W<br>";
    case "gas":
        $total_load += 0; // no appreciable electrical load
        break;
    case "water_heater_wattage":
        if (isset($_POST['user_provided_water_heater_wattage']) && !empty($_POST['user_provided_water_heater_wattage'])) {
            $user_provided_wattage = intval($_POST['user_provided_water_heater_wattage']);
            $loadToAdd = $isElectricStoveSelected ? ($user_provided_wattage * 0.25) : $user_provided_wattage;
            $total_load += $loadToAdd;
            $water_heater_w = $loadToAdd;
            // Debugging Echoes
            $output .= "Water Heater Type: Electric (Custom)<br>";
            if (!$isElectricStoveSelected) {
                $output .= "ADD: Water Heater: " . $user_provided_wattage . " W<br>";
            } else {
                $output .= "Water Heater: " . $user_provided_wattage . " W (before adjustment)<br>";
                $output .= "ADD (after auto adjustment due to electric stove): " . ($user_provided_wattage * 0.25) . " W<br>";
            }
            $output .= "Running total: " . $total_load . " W<br>";
        }
        break;

    default:
        // Optionally handle unexpected cases
        break;
}

//


// Clothes Dryer
$clothes_dryer_type = $_POST['clothes_dryer'];

switch ($clothes_dryer_type) {
    case "electric":
        // if electric stove, only use 25%
        // https://products.geappliances.com/appliance/gea-support-search-content?contentId=34592
        //$loadToAdd = $isElectricStoveSelected ? (5600 * 0.25) : 5600; 
        $loadToAdd = $isElectricStoveSelected ? (5760 * 0.25) : 5760; // 80% of 30A circuit
        $total_load += $loadToAdd;
        $clothes_dryer_w = $loadToAdd;
        // Debugging Echoes
        $output .= "Clothes Dryer Type: electric<br>";
        if (!$isElectricStoveSelected) {
            $output .= "ADD: Clothes Dryer: 5760 W<br>";
            $output .= "Running total: " . $total_load . " W<br>";
        } else {
            $output .= "Clothes Dryer: 5760 W (before adjustment)<br>";
            $output .= "ADD (after auto adjustment due to electric stove): " . (5760 * 0.25) . " W<br>";
            $output .= "Running total: " . $total_load . " W<br>";
        }
        break;
    case "gas":
    case "heatpump":
        $total_load += 0; // covered under base load
        break;
    case "clothes_dryer_wattage":
        if (isset($_POST['user_provided_clothes_dryer_wattage']) && !empty($_POST['user_provided_clothes_dryer_wattage'])) {
            $user_provided_wattage = intval($_POST['user_provided_clothes_dryer_wattage']);
            $loadToAdd = $isElectricStoveSelected ? ($user_provided_wattage * 0.25) : $user_provided_wattage;
            $total_load += $loadToAdd;
            $clothes_dryer_w = $loadToAdd;
            // Debugging Echoes
            $output .= "Clothes Dryer Type: electric (Custom)<br>";
            if (!$isElectricStoveSelected) {
                $output .= "ADD: Clothes Dryer: " . $user_provided_wattage . " W<br>";
                $output .= "Running total: " . $total_load . " W<br>";
            } else {
                $output .= "Clothes Dryer: " . $user_provided_wattage . " W (before adjustment)<br>";
                $output .= "ADD (after auto adjustment due to electric stove): " . ($user_provided_wattage * 0.25) . " W<br>";
                $output .= "Running total: " . $total_load . " W<br>";
            }
        }
        break;
}

//$output .= "Running total: " . $total_load . " W<br>";





// dishwasher commented out, part of base load calc
// // dishwasher
// echo "Load before Dishwasher calculation: " . $total_load . " W<br>";
// if(isset($_POST['dishwasher'])) {
//     if (isset($_POST['user_provided_dishwasher_wattage']) && !empty($_POST['user_provided_dishwasher_wattage'])) {
//         $dishwasher_wattage = intval($_POST['user_provided_dishwasher_wattage']);
//     } else {
//         $dishwasher_wattage = 1800; // default value
//     }
//     $total_load += $dishwasher_wattage;
// }
// echo "Load after Dishwasher calculation: " . $total_load . " W<br>";





// hottub / other
// https://homeinspectioninsider.com/how-many-amps-does-a-hot-tub-use/
if(isset($_POST['hottub']) && $_POST['hottub'] === 'yes') {
    if (isset($_POST['user_provided_hottub_wattage']) && !empty($_POST['user_provided_hottub_wattage'])) {
        $hottub_wattage = intval($_POST['user_provided_hottub_wattage']);
    } else {
        $hottub_wattage = 12000; // default value, update HTML form if altered
    }
    $total_load += $hottub_wattage;
    $output .= "ADD: Hot Tub: " . $hottub_wattage . " W<br>";
    $output .= "Running total: " . $total_load . " W<br>";
}


// infloor_heat
// https://thehomeans.com/how-many-amps-does-a-heated-floor-use/#What%20Size%20Breaker%20Do%20I%20Need%20For%20Underfloor%20Heating?

if(isset($_POST['infloor_heat']) && $_POST['infloor_heat'] === 'yes') {

    if (isset($_POST['user_provided_infloor_heat_wattage']) && !empty($_POST['user_provided_infloor_heat_wattage'])) {
        $infloor_heat_wattage = intval($_POST['user_provided_infloor_heat_wattage']);
    } else {
        $infloor_heat_wattage = 7680; // default value
    }
    $total_load += $infloor_heat_wattage;
    $output .= "ADD: In-Floor Heating: " . $infloor_heat_wattage . " W<br>";
    $output .= "Running total: " . $total_load . " W<br>";
}

// service delivery
$userSelectedOption = $_POST['service_delivery']; // Assuming POST method

// Set $service_delivery based on the user's choice
if (isset($userSelectedOption)) {
    // If an option is selected, use its value
    $service_delivery = $userSelectedOption;
    $output .= "Electrical service delivered: $service_delivery ground<br>";
} else {
    // Set a default value (e.g., if no option is selected)
    $service_delivery = 'unknown'; // Adjust as needed
}
// Now you can use $service_delivery in your further processing








    // Calculate remaining capacity
    $remaining_capacity = $panel_capacity - $total_load;
$output .= "<br>";
$output .= "Total Load: " . $total_load . " W<br>";
$output .= "Panel Capacity: " . $panel_capacity . " W<br>";
$output .= "Available Capacity: " . $remaining_capacity . " W<br>";




}

// Assuming all required variables ($remaining_capacity, $stove_w, $clothes_dryer_w, $water_heater_w, and $ev_chargers) are defined above this snippet.

$best_fit_charger = null;
$shared_circuit_message = "";

// Try to find a charger that fits without sharing circuits
foreach (array_reverse($ev_chargers) as $charger) {
    if ($remaining_capacity >= $charger['wattage']) {
        $best_fit_charger = $charger;
        break; // Found a suitable charger without sharing
    }
}

// If no charger fits, try considering sharing circuits with high-wattage appliances
if (!$best_fit_charger) {
    $appliance_wattages = [
        'stove' => $stove_w,
        'clothes dryer' => $clothes_dryer_w,
        'water heater' => $water_heater_w
    ];

    foreach ($appliance_wattages as $appliance => $wattage) {
        $temp_capacity = $remaining_capacity + $wattage; // Consider sharing the circuit
        foreach (array_reverse($ev_chargers) as $charger) {
            if ($temp_capacity >= $charger['wattage']) {
                $best_fit_charger = $charger;
                
                // Check the $service_delivery status to customize the message
                if ($service_delivery == 'above') {
                    $shared_circuit_message = "This will require either upgrading your electrical service OR sharing the electrical circuit with your $appliance using an Energy Management System/similar device (available from your electrician) OR a smart EV charger. As costs may be comparable, a service upgrade is recommended as it offers fastest charging and future growth potential.";
                } else {
                    $shared_circuit_message = "This will require sharing the electrical circuit with your $appliance using an Energy Management System/similar device (available from your electrician) OR a smart EV charger.";
                }
                
                break 2; // Found a suitable charger with sharing, exit both loops
            }
        }
    }
}


// Construct the output message
$message = ''; // Initialize message as empty
if ($formSubmitted) { // Only construct the message if the form has been submitted
    if ($best_fit_charger) {
        $message = "<img src=\"https://upload.wikimedia.org/wikipedia/commons/3/3b/Eo_circle_green_checkmark.svg\" alt=\"Green checkmark\" width=\"20\" height=\"20\">
        <strong>The best fit EV charger for your setup is: {$best_fit_charger['amperage']}A ({$best_fit_charger['kW']}kW), " .
                   "adding roughly {$best_fit_charger['kmPerHour']}km/h, with a full charge in {$best_fit_charger['fullChargeTime']} (based on a typical electric sedan).<p> $shared_circuit_message </strong><p>Note: A full charge is seldom required, as EVs often have more range than will be used daily.";
    } else {
        if ($service_delivery === 'above') {
            $message = "<img src=\"https://upload.wikimedia.org/wikipedia/commons/5/5f/Red_X.svg\" alt=\"Red X\" width=\"20\" height=\"20\">
            <strong>Based on the provided details, you might need to upgrade your electrical service to add an EV charger. Budget roughly $2000 since your residence is connected to an outdoor power pole.</strong>";
        } else {
            $message = "<img src=\"https://upload.wikimedia.org/wikipedia/commons/5/5f/Red_X.svg\" alt=\"Red X\" width=\"20\" height=\"20\">
            <strong>Based on the provided details, you might need to upgrade your electrical service to add an EV charger. Contact an electrician for quotes.</strong>";
        }
    }
}







// Your HTML form
?><?php
session_start();
// Reset form data (if submitted)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset'])) {
    $_SESSION = array(); // Clear the session variables
    header('Location: ' . $_SERVER['PHP_SELF']); // Redirect to the same page
    exit; // Exit script execution
  }
  
//   // Handle clothes dryer selection (if submitted)
//   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     if ($_POST['clothes_dryer'] === 'clothes_dryer_wattage') {
//       $_SESSION['user_specified_wattage'] = 'clothes_dryer_wattage';
//     } else {
//       // Unset session variable if not selected
//       unset($_SESSION['user_specified_wattage']);
//     }
//   }

// below was working. commented out as trying to hide/show fields
// if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset'])) {
//     $_SESSION = array(); // Clear the session variables
//     header('Location: ' . $_SERVER['PHP_SELF']); // Redirect to the same page to refresh and clear form data
//     exit;
// }
?>

        
<style>
    /* General form styling */
    .form-class {
        background-color: #f2f2f2;
        padding: 20px;
        border-radius: 5px;
        max-width: 1000px;
        margin: auto;
    }

    /* Input, Select fields, and Textarea styling */
    .form-class input[type="number"],
    .form-class input[type="email"],
    .form-class select,
    .form-class textarea {
        width: 35%;
        padding: 12px 20px;
        margin: 8px 0;
        display: inline-block;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }

    /* Submit button styling */
    .form-class input[type="submit"] {
        background-color: #4CAF50; /* Green */
        color: white;
        padding: 14px 20px;
        margin: 8px 0;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
    }

    .form-class input[type="submit"]:hover {
        background-color: #45a049; /* Darker Green */
    }

    /* Reset button styling, using type="submit" */
    .form-class input[type="submit"].reset-button {
        background-color: #FFD700; /* Yellow */
        color: white;
    }

    .form-class input[type="submit"].reset-button:hover {
        background-color: #ccac00; /* Darker Yellow */
    }

    /* Media query for devices with width less than or equal to 600px */
    @media screen and (max-width: 600px) {
        .form-class input[type="number"],
        .form-class input[type="email"],
        .form-class select,
        .form-class textarea,
        .form-class #heating {
            width: 60%; /* Make input fields, select boxes, and heating dropdown fill the container */
        }

        /* Custom adjustments for specific fields to be narrower */
        .form-class #panel_capacity_amps,
        .form-class #home_size,
        .form-class #home_size_unit {
            width: calc(35% - 24px); /* Adjusted width for these elements */
        }
    }
    /* Get certain itmes together on same line*/
    .input-container {
    display: flex;
    align-items: center;
}
label[id$="_wattage_label"] {
    margin-right: 8px; /* Adjust the value as needed */
}

/* Pop up for Panel Capacity*/
.popup-info {
  position: absolute;
  background-color: #f9f9f9;
  box-shadow: 0 0 5px #ccc;
  padding: 10px;
  z-index: 100;
  width: 300px;
  border-radius: 5px;
}
.info-icon {
  cursor: pointer;
  display: inline;
}

</style>

<div class="form-class">
<form action="" method="post" id="calcForm">



<label for="panel_capacity_amps" title="This is your breaker box, often in a basement. Size is often identified by the top breaker, and is typically one of: 60, 100, 150, 200">
    Panel Capacity:
</label>
<a href="javascript:void(0);" onclick="showInfoPopup();" style="text-decoration:none;"> (?)</a>

<div id="infoPopup" style="display:none; position: fixed; left: 50%; top: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border: 1px solid #ddd; z-index: 1000;">
    <p>For more information on determining the size of your home's electrical service, visit:</p>
    <a href="https://www.thespruce.com/electrical-service-size-of-my-home-1152752" target="_blank">The Spruce: Electrical Service Size of My Home</a>
    <br><br>
    <button onclick="closeInfoPopup()">Close</button>
</div>

<script>
function showInfoPopup() {
    document.getElementById("infoPopup").style.display = "block";
}

function closeInfoPopup() {
    document.getElementById("infoPopup").style.display = "none";
}
</script>





    <input type="number" id="panel_capacity_amps" name="panel_capacity_amps" value="<?php echo isset($_POST['panel_capacity_amps']) ? $_POST['panel_capacity_amps'] : ''; ?>" placeholder="input value" required> Amps<br>

    <label for="home_size">Size of home:</label>
    <input type="number" id="home_size" name="home_size" required value="<?php echo isset($_POST['home_size']) ? $_POST['home_size'] : ''; ?>" placeholder="input value">
    <select name="home_size_unit" id="home_size_unit">
    <option value="sqft" <?php echo (isset($_POST['home_size_unit']) && $_POST['home_size_unit'] == 'sqft') ? 'selected' : ''; ?>>sq ft</option>
    <option value="m2" <?php echo (isset($_POST['home_size_unit']) && $_POST['home_size_unit'] == 'm2') ? 'selected' : ''; ?>>m²</option>
</select>

    <br>

    <label for="heating">Heating:</label>
<select name="heating" id="heating">
    <option value="gas" <?php echo (isset($_POST['heating']) && $_POST['heating'] == 'gas') ? 'selected' : ''; ?>>Gas Furnace</option>
    <option value="electric" <?php echo (isset($_POST['heating']) && $_POST['heating'] == 'electric') ? 'selected' : ''; ?>>Electric Furnace</option>
    <option value="air_heat_pump" <?php echo (isset($_POST['heating']) && $_POST['heating'] == 'air_heat_pump') ? 'selected' : ''; ?>>Air Source Heat Pump</option>
    <option value="heating_wattage" <?php echo (isset($_POST['heating']) && $_POST['heating'] == 'heating_wattage') ? 'selected' : ''; ?>>Custom or None</option>
</select>



    </select>
    <label for="user_provided_heating_wattage" id="user_provided_heating_wattage_label" style="display:none;">Custom value (W):</label>
    <input type="number" name="user_provided_heating_wattage" id="user_provided_heating_wattage" style="display: none;" placeholder="Input value or 0 if none">
    <br>

    <label for="stove">Stove:</label>
<select name="stove" id="stove" onchange="showWattageInput(this.value);">
    <option value="gas" <?php echo (isset($_POST['stove']) && $_POST['stove'] == 'gas') ? 'selected' : ''; ?>>Gas</option>
    <option value="electric" <?php echo (isset($_POST['stove']) && $_POST['stove'] == 'electric') ? 'selected' : ''; ?>>Electric</option>
    <option value="stove_wattage" <?php echo (isset($_POST['stove']) && $_POST['stove'] == 'stove_wattage') ? 'selected' : ''; ?>>Custom or None</option>
</select>
    <!-- Input field for user-provided stove wattage -->
    <div class="input-container" id="stove_wattage_container">
    <label for="user_provided_stove_wattage" id="user_provided_stove_wattage_label" style="<?php echo $selectedStoveValue == 'stove_wattage' ? '' : 'display:none;'; ?>">Custom value (W):</label>
    <input type="number" name="user_provided_stove_wattage" id="user_provided_stove_wattage" style="<?php echo $selectedStoveValue == 'stove_wattage' ? '' : 'display: none;'; ?>" placeholder="Input value or 0 if none">
</div>




    <label for="water_heater">Water Heater:</label>
<select name="water_heater" id="water_heater">
    <option value="gas" <?php echo (isset($_POST['water_heater']) && $_POST['water_heater'] == 'gas') ? 'selected' : ''; ?>>Gas</option>
    <option value="electric" <?php echo (isset($_POST['water_heater']) && $_POST['water_heater'] == 'electric') ? 'selected' : ''; ?>>Electric Tank</option>
    <option value="tankless_water_heater" <?php echo (isset($_POST['water_heater']) && $_POST['water_heater'] == 'tankless_water_heater') ? 'selected' : ''; ?>>Electric Tankless</option>
    <option value="water_heater_wattage" <?php echo (isset($_POST['water_heater']) && $_POST['water_heater'] == 'water_heater_wattage') ? 'selected' : ''; ?>>Custom or None</option>
</select>
<!-- Input field for user-entered wattage -->
<div class="input-container" id="water_heater_wattage_container">
<label for="user_provided_water_heater_wattage" id="user_provided_water_heater_wattage_label" style="display:none;">Custom value (W):</label>
<input type="number" name="user_provided_water_heater_wattage" id="user_provided_water_heater_wattage" style="display: none;" placeholder="Input value or 0 if none">
</div>






<label for="clothes_dryer">Dryer:</label>
<select name="clothes_dryer" id="clothes_dryer">
    <option value="gas" <?php echo (isset($_POST['clothes_dryer']) && $_POST['clothes_dryer'] == 'gas') ? 'selected' : ''; ?>>Gas</option>
    <option value="electric" <?php echo (isset($_POST['clothes_dryer']) && $_POST['clothes_dryer'] == 'electric') ? 'selected' : ''; ?>>Electric</option>
	<option value="heatpump" <?php echo (isset($_POST['clothes_dryer']) && $_POST['clothes_dryer'] == 'heatpump') ? 'selected' : ''; ?>>Electric Heat Pump</option>
    <option value="clothes_dryer_wattage" <?php echo (isset($_POST['clothes_dryer']) && $_POST['clothes_dryer'] == 'clothes_dryer_wattage') ? 'selected' : ''; ?>>Custom or None</option>
</select>
<!-- Input field for user-provided clothes dryer wattage -->
<div class="input-container" id="clothes_dryer_wattage_container">
<label for="user_provided_clothes_dryer_wattage" id="user_provided_clothes_dryer_wattage_label" style="display: none;">Custom Value (W):</label>
<input type="number" name="user_provided_clothes_dryer_wattage" id="user_provided_clothes_dryer_wattage"  value="<?php echo isset($_POST['user_provided_clothes_dryer_wattage']) ? $_POST['user_provided_clothes_dryer_wattage'] : ''; ?>" style="display: none;" placeholder="Input value or 0 if none"><br>
</div>




<label for="ac_yes">Do you have Air Conditioning?</label>
<input type="radio" id="ac_yes" name="ac" value="yes" >
<label for="ac_yes">Yes</label>
<input type="radio" id="ac_no" name="ac" value="no"  checked>
<label for="ac_no">No</label><br>

<label for="user_provided_ac_wattage" id="user_provided_ac_wattage_label" style="display:none;">Leave blank to use estimated value or provide equipment's wattage:</label>
<input type="number" id="user_provided_ac_wattage" name="user_provided_ac_wattage" style="display:none;" placeholder="Leave blank for estimate">



<!--  part of baseload calc
    <label for="dishwasher_yes">Do you have a dishwasher?</label>
    <input type="radio" id="dishwasher_yes" name="dishwasher" value="yes">
    <label for="dishwasher_yes">Yes</label>
    <input type="radio" id="dishwasher_no" name="dishwasher" value="no" checked>
    <label for="dishwasher_no">No</label><br>
    <label for="user_provided_dishwasher_wattage" style="display:none;">Dishwasher Wattage (if known):</label>
<input type="number" id="user_provided_dishwasher_wattage" name="user_provided_dishwasher_wattage" style="display:none;">
-->

    <label for="hottub_yes">Do you have a hot tub/pool/spa?</label>
    <input type="radio" id="hottub_yes" name="hottub" value="yes">
    <label for="hottub_yes">Yes</label>
    <input type="radio" id="hottub_no" name="hottub" value="no" checked>
    <label for="hottub_no">No</label><br>
    <label for="user_provided_hottub_wattage" style="display:none;">Leave blank to use Hot Tub estimated value or provide total wattage of all such equipment:</label>
<input type="number" id="user_provided_hottub_wattage" name="user_provided_hottub_wattage" style="display:none;" placeholder="Leave blank for estimate">



    <label for="infloor_heat_yes">Do you have electric in-floor heating?</label>
    <input type="radio" id="infloor_heat_yes" name="infloor_heat" value="yes">
    <label for="infloor_heat_yes">Yes</label>
    <input type="radio" id="infloor_heat_no" name="infloor_heat" value="no" checked>
    <label for="infloor_heat_no">No</label><br>
    <label for="user_provided_infloor_heat_wattage" style="display:none;">Leave blank to use estimated value or provide equipment's wattage:</label>
<input type="number" id="user_provided_infloor_heat_wattage" name="user_provided_infloor_heat_wattage" style="display:none;" placeholder="Leave blank for estimate">



<label for="service_delivery">Is your electrical service delivered:</label>
<select name="service_delivery" id="service_delivery">
    <option disabled selected value> -- select an option -- </option>
    <option value="above" <?php if ($service_delivery === 'above') echo 'selected'; ?>>Above Ground (visible power lines)</option>
    <option value="under" <?php if ($service_delivery === 'under') echo 'selected'; ?>>Under Ground</option>
</select>


   
    <br>

    <input type="submit" name="submit" value="Calculate">
    


<input type="submit" name="reset" value="Reset" class="reset-button"  formnovalidate></form>
    </div>


<script>
document.addEventListener("DOMContentLoaded", function() {
    
    
    function resetFormAndStorage() {
        // Clear localStorage and sessionStorage items
        localStorage.clear();
        sessionStorage.clear();
        
        // Optionally, reset any specific values or settings if needed
        // For example, reset form fields to default values if not automatically handled

        // Refresh the page to apply default values
        window.location.reload();
    }

    
    
    
    function handleFeatureChange(feature, wattageInputId) {
        const featureSelection = document.querySelector(`input[name="${feature}"]:checked`)?.value;
        const wattageField = document.getElementById(wattageInputId);
        const wattageLabel = document.querySelector(`label[for="${wattageInputId}"]`);

        if (featureSelection === 'yes') {
            wattageField.style.display = 'block';
            wattageLabel.style.display = 'block';
            wattageField.value = localStorage.getItem(`${feature}Wattage`) || '';
        } else {
            wattageField.style.display = 'none';
            wattageLabel.style.display = 'none';
            // Reset wattageField value to default if needed
        }

        document.querySelectorAll(`input[name="${feature}"]`).forEach(input => {
            input.addEventListener('change', () => {
                const selectedValue = document.querySelector(`input[name="${feature}"]:checked`).value;
                localStorage.setItem(`${feature}Selection`, selectedValue);
                handleFeatureChange(feature, wattageInputId);
            });
        });

        wattageField.addEventListener('input', () => {
            localStorage.setItem(`${feature}Wattage`, wattageField.value);
        });
    }

    function handleDropdownChange(dropdownId, customValue, wattageInputId, wattageLabelId) {
        const dropdown = document.getElementById(dropdownId);
        const wattageInput = document.getElementById(wattageInputId);
        const wattageLabel = document.getElementById(wattageLabelId);

        dropdown.addEventListener('change', function() {
            if (this.value === customValue) {
                wattageInput.style.display = 'block';
                wattageLabel.style.display = 'block';
            } else {
                wattageInput.style.display = 'none';
                wattageLabel.style.display = 'none';
            }
            localStorage.setItem(`${dropdownId}Selection`, this.value);
        });

        const savedSelection = localStorage.getItem(`${dropdownId}Selection`) || dropdown.value;
        if (savedSelection === customValue) {
            wattageInput.style.display = 'block';
            wattageLabel.style.display = 'block';
        } else {
            wattageInput.style.display = 'none';
            wattageLabel.style.display = 'none';
        }
        dropdown.value = savedSelection;

        wattageInput.addEventListener('input', function() {
            localStorage.setItem(`${wattageInputId}Value`, this.value);
        });

        const savedWattage = localStorage.getItem(`${wattageInputId}Value`);
        if (savedWattage) {
            wattageInput.value = savedWattage;
        }
        
    }

    // Initialize form fields and selections
    ['ac', 'hottub', 'infloor_heat'].forEach(feature => {
        const selection = localStorage.getItem(`${feature}Selection`) || 'no';
        document.querySelectorAll(`input[name="${feature}"]`).forEach(input => {
            if (input.value === selection) {
                input.checked = true;
            }
        });

        const wattageInputId = `user_provided_${feature}_wattage`;
        handleFeatureChange(feature, wattageInputId);
    });

    handleDropdownChange('heating', 'heating_wattage', 'user_provided_heating_wattage', 'user_provided_heating_wattage_label');
    handleDropdownChange('stove', 'stove_wattage', 'user_provided_stove_wattage', 'user_provided_stove_wattage_label');
    handleDropdownChange('water_heater', 'water_heater_wattage', 'user_provided_water_heater_wattage', 'user_provided_water_heater_wattage_label');
    handleDropdownChange('clothes_dryer', 'clothes_dryer_wattage', 'user_provided_clothes_dryer_wattage', 'user_provided_clothes_dryer_wattage_label');




    // Handling Reset Button Click
    document.querySelector('[name="reset"]').addEventListener('click', resetFormAndStorage);





    // Check for form submission flag and SCROLL if set
    if (sessionStorage.getItem('formSubmitted') === 'true') {
        var formElement = document.getElementById('service_delivery'); //scroll to 'service_delivery ID
        if(formElement) {
            formElement.scrollIntoView({ behavior: 'smooth' });
        }
        // Optionally clear the flag if you only want to scroll once per submission
        sessionStorage.removeItem('formSubmitted');
    }

    // Set the form submission flag when the form is submitted
    var form = document.getElementById('calcForm');
    if(form) {
        form.addEventListener('submit', function() {
            sessionStorage.setItem('formSubmitted', 'true');
            // No need to manually scroll here; the page will reload or navigate,
            // and scrolling will occur based on the flag set above.
        });
    }
    // Validation for empty values
    // Custom validation function
    function validateForm() {
        let isValid = true;

        // Heating wattage validation
        const heatingWattageInput = document.getElementById('user_provided_heating_wattage');
        if(heatingWattageInput.style.display !== 'none' && !heatingWattageInput.value) {
            alert('Please input the Heating custom wattage (input "0" if you do not have one) or select another Heating option.');
            isValid = false;
        }

        // Stove wattage validation
        const stoveWattageInput = document.getElementById('user_provided_stove_wattage');
        if(stoveWattageInput.style.display !== 'none' && !stoveWattageInput.value) {
            alert('Please input the Stove custom wattage (input "0" if you do not have one) or select another Stove option.');
            isValid = false;
        }

        // Water heater wattage validation
        const waterHeaterWattageInput = document.getElementById('user_provided_water_heater_wattage');
        if(waterHeaterWattageInput.style.display !== 'none' && !waterHeaterWattageInput.value) {
            alert('Please input the Water Heater wattage (input "0" if you do not have one) or select another Water Heater option.');
            isValid = false;
        }

        // Dryer wattage validation
        const dryerHeaterWattageInput = document.getElementById('user_provided_clothes_dryer_wattage');
        if(dryerHeaterWattageInput.style.display !== 'none' && !dryerHeaterWattageInput.value) {
            alert('Please input the Dryer wattage (input "0" if you do not have one) or select another Dryer option.');
            isValid = false;
        }

        return isValid;
    }

    // Modify form submission event listener
    if(form) {
        form.addEventListener('submit', function(event) {
            if(!validateForm()) {
                event.preventDefault(); // Prevent form submission if validation fails
            }
            sessionStorage.setItem('formSubmitted', 'true');
        });
    }


});


</script>

















    <?php

// The HTML form and display results sections can then follow as previously described.

// Display feedback message after form submission
if($message) {
    echo '<div style="text-align: center; margin: 0 auto; width: 80%;">';
    echo "<p>" . $message . "</p>";
    echo '</div>';
}

// Wrap the output in a container div and apply CSS to center it
echo '<div style="text-align: center; margin: 0 auto; width: 80%;">';
echo "<p>Output:<br>" . $output . "</p>";
echo '</div>';



    
    return ob_get_clean(); // End output buffering and return everything
}

add_shortcode('load_calculator', 'load_calculator_form_shortcode'); 