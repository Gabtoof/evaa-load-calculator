<?php
/*
Plugin Name: EVAA Load Calculator
Description: A plugin to calculate the electrical load for adding an EV charger.
Version: 1.0
Author: Andrew Baituk
*/

// using data from https://www.edmonton.ca/sites/default/files/public-files/assets/PDF/Electrical_Inspection_Load_Calculation.pdf?cb=1625176204
// actually, https://iaeimagazine.org/2013/mayjune-2013/residential-load-calculations/ has even better/easier to understand data

/* Todo:
[ ] test AC changes
[*] Fix show of watts
[*] figure out 'additional loads' like electric dryer, electric water heater, 
[*] user larger of heat/ac
[*] get sources for: hot tub
[*] in floor heat
[*] gas water heater (likely 0 for calc)
[*] tankless water heater - removed as this can be gas or electric and thus covered under those
[*] gas dryer (likely 0)
[*] add heat pump dryer
[ ] gas stove
[*] remove dishwasher
[ ] test if m2 used, conversion (seems ok if sq ft used)
[ ] fix hot tub/in floor heat being added even if no
[ ] show default values to be used
[ ] expand info on EV charger - suggest lower charging when needed
[ ] suggest load balancer/loadmiser when applicable
[ ] get someone to double check logic
[ ] add disclaimers/etc
[ ] make pretty

*/




// Enqueue the JS script
function evaa_load_calculator_scripts() {
    // Use the WordPress built-in script versioning for cache-busting
    $version = wp_get_theme()->get('Version');

    wp_enqueue_script(
        'evaa-calculator',
        plugin_dir_url(__FILE__) . 'evaa-load-calculator.js',
        array('jquery'), // Dependencies
       // $version, // Version number for cache busting
        true // Load in footer
    );
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
$ev_chargers = [
    ["amperage" => 12, "wattage" => 2800, "kmPerHour" => 19, "fullChargeTime" => "approx 14h"],
    ["amperage" => 16, "wattage" => 3800, "kmPerHour" => 24, "fullChargeTime" => "approx 10.75h"],
    ["amperage" => 24, "wattage" => 5700, "kmPerHour" => 37, "fullChargeTime" => "approx 7h"],
    ["amperage" => 32, "wattage" => 7600, "kmPerHour" => 50, "fullChargeTime" => "approx 5.25h"],
    ["amperage" => 40, "wattage" => 9600, "kmPerHour" => 61, "fullChargeTime" => "approx 4.25h"],
    ["amperage" => 48, "wattage" => 11500, "kmPerHour" => 74, "fullChargeTime" => "approx 3.5h"]
];

// If the form has been submitted
if(isset($_POST['submit'])) {






    
    // Service Panel Capacity in Amps
$panel_capacity_amps = intval($_POST['panel_capacity_amps']);

// Convert to Watts (assuming 240V)
$panel_capacity = $panel_capacity_amps * 240;

//echo "Original Home Size: " . $home_size . " " . $home_size_unit . "<br>";
$output .= "Original Home Size: " . $home_size . " " . $home_size_unit . "<br>";

// Convert home size to m² and sqft
$home_size_m2 = ($home_size_unit == "sqft") ? $home_size * 0.092903 : $home_size;
$home_size_sqft = ($home_size_unit == "m2") ? $home_size * 10.764 : $home_size;

$output .= "Converted Home Size in m2: " . $home_size_m2 . " m2<br>";
$output .= "Converted Home Size in sqft: " . $home_size_sqft . " sqft<br>";

// Living Area load calculation based on m²
// Accepting that this doesn't factor in 75% for basements. That adds complexity to user and this will give us only a slightly more 'conservative' result (more likely to say EV charger may not 'fit')
if($home_size_m2 <= 90) {
$total_load += 5000;
} else {
$total_load += 5000 + (1000 * ceil(($home_size_m2 - 90)/90));
}
  
$output .= "Base Living Area Load: 5000W<br>";
$output .= "Additional Living Area Load: " . ($total_load - 5000) . "W<br>";
$output .= "Total Living Area Load: " . $total_load . "W<br>";

// Before heating/AC calculation
$output .= "Load before heating calculation: " . $total_load . "W<br>";
// Heating

$heating_type = $_POST['heating'];
switch($heating_type) {
    case "gas":  //https://iaeimagazine.org/2013/mayjune-2013/residential-load-calculations/
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
    case "heating_watt":
        $heating_load = intval($_POST['user_provided_heating_wattage']);
        break;
}


// AC calculation
// https://www.thisoldhouse.com/heating-cooling/reviews/what-size-air-conditioner-do-i-need
//https://www.electricalcalculators.org/air-conditioner-power-consumption-calculator/#:~:text=Answer%3A%202%20Ton%20ac%20%3D%202400%20watt%20%3D,of%20%240.2%2FkWh%20%3D%207.2%20kWh%20%2A%240.2%2FkWh%20%3D%20%241.44
// 1500sqft 2ton 2400w, 2200 2.75t 3300w, 3000 3.5t 4200w
if(isset($_POST['ac']) && $_POST['ac'] === 'yes') {
    // If AC wattage is provided by user, use that; otherwise, use default
    if (isset($_POST['user_provided_ac_wattage']) && !empty($_POST['user_provided_ac_wattage'])) {
        $ac_load = intval($_POST['user_provided_ac_wattage']);
    } else {
        // Assuming $heating_type is determined before this code snippet
        if ($heating_type == "gas") {
            if ($home_size_sqft <= 1500) {
                $ac_load = 2400;
            } elseif ($home_size_sqft > 1500 && $home_size_sqft <= 3000) {
                $ac_load = 3300;
            } else {
                $ac_load = 4200;
            }
        }
    }
}


// Decide which load to add based on which is larger
if ($ac_load > $heating_load) {
    $total_load += $ac_load;
    // Debugging output
    $output .= "AC Load is larger. AC Load: {$ac_load} Watts, Heating Load: {$heating_load} Watts, Total Load: {$total_load} Watts.";
} else {
    $total_load += $heating_load;
    // Debugging output
    $output .= "Heating Load is larger or equal. AC Load: {$ac_load} Watts, Heating Load: {$heating_load} Watts, Total Load: {$total_load} Watts.";
}

$output .= "Load After HVAC calculation: " . $total_load . "W<br>";

$output .= "Load before Stove calculation: " . $total_load . "W<br>";
// Stove
$stove_type = $_POST['stove'];

switch($stove_type) {
    case "electric":
        // https://iaeimagazine.org/2013/mayjune-2013/residential-load-calculations/
        // https://www.lg.com/ca_en/cooking-appliances/ranges/lsil6336f/
        // $total_load += 12000; // 80% of a 40A circuit
        $total_load += 6000; // 6kW allowance up to 12kW actual per https://iaeimagazine.org/2013/mayjune-2013/residential-load-calculations/
        break;
    case "gas":
        
        $total_load += 0; // covered in base load - less than 1500W
        break;

    case "stove_wattage":
        if (isset($_POST['user_provided_stove_wattage']) && !empty($_POST['user_provided_stove_wattage'])) {
            $total_load += intval($_POST['user_provided_stove_wattage']);
        }
        break;
    // Add cases for other types if necessary
}
// Flag to indicate if the electric stove is selected
$isElectricStoveSelected = ($stove_type === "electric");
$output .= "Stove Type: " . $stove_type . "<br>";
$isElectricStoveSelected = ($stove_type === "electric");
$output .= "Is Electric Stove Selected: " . ($isElectricStoveSelected ? "Yes" : "No") . "<br>";



$output .= "Load after Stove calculation: " . $total_load . "W<br>";


$output .= "Load before Water Heater calculation: " . $total_load . "W<br>";

// Water Heater
$water_heater_type = $_POST['water_heater'];

switch($water_heater_type) {
    case "electric":
        // https://solvitnow.com/blog/what-size-breaker-do-i-need-for-my-water-heater/
        // if electric stove, only use 25%
        $loadToAdd = $isElectricStoveSelected ? (5760 * 0.25) : 5760; // Default wattage for electric water heater
        $total_load += $loadToAdd; 
        $output .= "Electric Stove Selected: " . ($isElectricStoveSelected ? "Yes" : "No") . "<br>";
        $output .= "Load to Add: " . $loadToAdd . "W<br>";
        break;
    case "gas":
        
        $total_load += 0; // no appreciable electricl load
        break;

    case "water_heater_wattage": // This case should match the value attribute from the HTML select option
        // Check if the user provided a custom wattage
        if (isset($_POST['user_provided_water_heater_wattage']) && !empty($_POST['user_provided_water_heater_wattage'])) {
            $user_provided_wattage = intval($_POST['user_provided_water_heater_wattage']);
            // Apply the 25% rule if electric stove is selected
            $loadToAdd = $isElectricStoveSelected ? ($user_provided_wattage * 0.25) : $user_provided_wattage;
            $total_load += $loadToAdd;
            $output .= "Electric Stove Selected: " . ($isElectricStoveSelected ? "Yes" : "No") . "<br>";
            $output .= "Custom Water Heater Wattage: " . $user_provided_wattage . "W<br>";
            $output .= "Load to Add (after adjustment if applicable): " . $loadToAdd . "W<br>";
        }
        break;
    default:
        // Optionally handle unexpected cases
        break;
}

$output .= "Load after Water Heater calculation: " . $total_load . "W<br>";

$output .= "Load before Clothes Dryer calculation: " . $total_load . "W<br>";

// Clothes Dryer
$clothes_dryer_type = $_POST['clothes_dryer'];

switch($clothes_dryer_type) {
    case "electric":
        // if electric stove, only use 25%
        // https://products.geappliances.com/appliance/gea-support-search-content?contentId=34592
        //$loadToAdd = $isElectricStoveSelected ? (5600 * 0.25) : 5600; 
        $loadToAdd = $isElectricStoveSelected ? (5760 * 0.25) : 5760; //80% of 30A circuit
        $total_load += $loadToAdd;
        // Debugging Echoes
        $output .= "Electric Stove Selected: " . ($isElectricStoveSelected ? "Yes" : "No") . "<br>";
        $output .= "Load to Add: " . $loadToAdd . "W<br>";
        break;
    case "gas":
        $total_load += 0; // covered under base load
        break;
    case "heatpump":
        $total_load += 0; // covered under base load
        break;
    case "clothes_dryer_wattage":
        if (isset($_POST['user_provided_clothes_dryer_wattage']) && !empty($_POST['user_provided_clothes_dryer_wattage'])) {
            $user_provided_wattage = intval($_POST['user_provided_clothes_dryer_wattage']);
            $loadToAdd = $isElectricStoveSelected ? ($user_provided_wattage * 0.25) : $user_provided_wattage;
            $total_load += $loadToAdd;
            $output .= "Custom Clothes Dryer Wattage: " . $loadToAdd . "W<br>";
        }
        break;
    // Add cases for other types if necessary
}

$output .= "Load after Clothes Dryer calculation: " . $total_load . "W<br>";



// dishwasher commented out, part of base load calc
// // dishwasher
// echo "Load before Dishwasher calculation: " . $total_load . "W<br>";
// if(isset($_POST['dishwasher'])) {
//     if (isset($_POST['user_provided_dishwasher_wattage']) && !empty($_POST['user_provided_dishwasher_wattage'])) {
//         $dishwasher_wattage = intval($_POST['user_provided_dishwasher_wattage']);
//     } else {
//         $dishwasher_wattage = 1800; // default value
//     }
//     $total_load += $dishwasher_wattage;
// }
// echo "Load after Dishwasher calculation: " . $total_load . "W<br>";





$output .= "Load before Hot Tub calculation: " . $total_load . "W<br>";
// hottub
// https://homeinspectioninsider.com/how-many-amps-does-a-hot-tub-use/
if(isset($_POST['hottub']) && $_POST['hottub'] === 'yes') {
    if (isset($_POST['user_provided_hottub_wattage']) && !empty($_POST['user_provided_hottub_wattage'])) {
        $hottub_wattage = intval($_POST['user_provided_hottub_wattage']);
    } else {
        $hottub_wattage = 12000; // default value
    }
    $total_load += $hottub_wattage;
}
$output .= "Load after Hot Tub calculation: " . $total_load . "W<br>";
$output .= "Load before Infloor Heating calculation: " . $total_load . "W<br>";
// infloor_heat
// https://thehomeans.com/how-many-amps-does-a-heated-floor-use/#What%20Size%20Breaker%20Do%20I%20Need%20For%20Underfloor%20Heating?

if(isset($_POST['infloor_heat']) && $_POST['infloor_heat'] === 'yes') {

    if (isset($_POST['user_provided_infloor_heat_wattage']) && !empty($_POST['user_provided_infloor_heat_wattage'])) {
        $infloor_heat_wattage = intval($_POST['user_provided_infloor_heat_wattage']);
    } else {
        $infloor_heat_wattage = 7680; // default value
    }
    $total_load += $infloor_heat_wattage;
}
$output .= "Load after Infloor Heating calculation: " . $total_load . "W<br>";











    // Calculate remaining capacity
    $remaining_capacity = $panel_capacity - $total_load;

$output .= "Panel Capacity: " . $panel_capacity . "W<br>";
$output .= "Total Load: " . $total_load . "W<br>";
$output .= "Remaining Capacity: " . $remaining_capacity . "W<br>";




}

// Find the best fit EV charger based on remaining capacity
$best_fit_charger = null;
foreach (array_reverse($ev_chargers) as $charger) {
    if ($remaining_capacity >= $charger["wattage"]) {
        $best_fit_charger = $charger;
        break; // Found the highest possible charger that fits
    }
}

// Construct the message based on the best fit charger found
if ($best_fit_charger) {
    $message = "Based on your remaining capacity of {$remaining_capacity}W, the best fit EV charger is: " .
               "{$best_fit_charger["amperage"]}A ({$best_fit_charger["wattage"]}W), adding " .
               "{$best_fit_charger["kmPerHour"]}km/h with a full charge in " .
               "{$best_fit_charger["fullChargeTime"]}.";
} else {
    $message = "Based on the provided details and your remaining capacity of {$remaining_capacity}W, " .
               "you might need to upgrade your panel to add an EV charger.";
}

echo $message;





// Your HTML form
?><?php
session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset'])) {
    $_SESSION = array(); // Clear the session variables
    header('Location: ' . $_SERVER['PHP_SELF']); // Redirect to the same page to refresh and clear form data
    exit;
}
?>

<form action="" method="post">
   
    <label for="panel_capacity_amps">Panel Capacity (in Amps):</label>
    <input type="number" id="panel_capacity_amps" name="panel_capacity_amps" required><br>

    <label for="home_size">Approx size of home (developed/livable area):</label>
    <input type="number" id="home_size" name="home_size" required>
    <select name="home_size_unit">
        <option value="sqft">sq ft</option>
        <option value="m2">m²</option>
    </select>
    <br>

    <label for="heating">Heating Type:</label>
    <select name="heating" id="heating">
        <option value="gas">Gas Furnace</option>
        <option value="electric">Electric Furnace</option>
        <option value="air_heat_pump">Air Source Heat Pump</option>
        <!-- removing until we can find a good source of data
        <option value="geo_heat_pump">Geothermal Heat Pump</option> 
        <option value="boiler">Boiler System</option> -->
        <option value="heating_wattage">I'll provide nameplate wattage:</option>
        <!-- Input field for heating wattage -->
        
    </select>
    <label for="user_provided_heating_wattage" id="user_provided_heating_wattage_label" style="display:none;">Watts:</label>
    <input type="number" name="user_provided_heating_wattage" id="user_provided_heating_wattage" style="display: none;">
    <br>
    <label for="stove">Stove:</label>
<select name="stove" id="stove">
    <option value="gas">Gas</option>
    <option value="electric">Electric</option>
    <option value="stove_wattage">I'll provide nameplate wattage:</option>
</select>
<!-- Input field for user-provided stove wattage -->
<label for="user_provided_stove_wattage" id="user_provided_stove_wattage_label" style="display:none;">Watts:</label>
<input type="number" name="user_provided_stove_wattage" id="user_provided_stove_wattage" style="display: none;"><br>

    <label for="water_heater">Water Heater Type:</label>
<select name="water_heater" id="water_heater">
    <option value="gas">Gas</option>
    <option value="electric">Electric</option>
    <option value="water_heater_wattage">I'll provide nameplate wattage:</option>
</select>
<!-- Input field for user-entered wattage -->
<label for="user_provided_water_heater_wattage" id="user_provided_water_heater_wattage_label" style="display:none;">Watts:</label>
<input type="number" name="user_provided_water_heater_wattage" id="user_provided_water_heater_wattage" style="display: none;">
<br>

<label for="clothes_dryer">Clothes Dryer Type:</label>
<select name="clothes_dryer" id="clothes_dryer">
    <option value="gas">Gas</option>
    <option value="electric">Electric</option>
    <option value="heatpump">Electric Heat Pump</option>
    <option value="clothes_dryer_wattage">I'll provide nameplate wattage:</option>
</select>
<!-- Input field for user-provided clothes dryer wattage -->
<label for="user_provided_clothes_dryer_wattage" id="user_provided_clothes_dryer_wattage_label" style="display:none;">Watts:</label>
<input type="number" name="user_provided_clothes_dryer_wattage" id="user_provided_clothes_dryer_wattage" style="display: none;"><br>


<br>

<label for="ac_yes">Do you have Air Conditioning?</label>
<input type="radio" id="ac_yes" name="ac" value="yes" >
<label for="ac_yes">Yes</label>
<input type="radio" id="ac_no" name="ac" value="no"  checked>
<label for="ac_no">No</label><br>

<label for="user_provided_ac_wattage" id="user_provided_ac_wattage_label" style="display:none;">Leave blank to use default or provide equipment's nameplate watt rating:</label>
<input type="number" id="user_provided_ac_wattage" name="user_provided_ac_wattage" style="display:none;">



<!--  part of baseload calc
    <label for="dishwasher_yes">Do you have a dishwasher?</label>
    <input type="radio" id="dishwasher_yes" name="dishwasher" value="yes">
    <label for="dishwasher_yes">Yes</label>
    <input type="radio" id="dishwasher_no" name="dishwasher" value="no" checked>
    <label for="dishwasher_no">No</label><br>
    <label for="user_provided_dishwasher_wattage" style="display:none;">Dishwasher Wattage (if known):</label>
<input type="number" id="user_provided_dishwasher_wattage" name="user_provided_dishwasher_wattage" style="display:none;">
-->

    <label for="hottub_yes">Do you have a hot tub?</label>
    <input type="radio" id="hottub_yes" name="hottub" value="yes">
    <label for="hottub_yes">Yes</label>
    <input type="radio" id="hottub_no" name="hottub" value="no" checked>
    <label for="hottub_no">No</label><br>
    <label for="user_provided_hottub_wattage" style="display:none;">Leave blank to use default or provide equipment's nameplate watt rating:</label>
<input type="number" id="user_provided_hottub_wattage" name="user_provided_hottub_wattage" style="display:none;">



    <label for="infloor_heat_yes">Do you have electric in-floor heating?</label>
    <input type="radio" id="infloor_heat_yes" name="infloor_heat" value="yes">
    <label for="infloor_heat_yes">Yes</label>
    <input type="radio" id="infloor_heat_no" name="infloor_heat" value="no" checked>
    <label for="infloor_heat_no">No</label><br>
    <label for="user_provided_infloor_heat_wattage" style="display:none;">Leave blank to use default or provide equipment's nameplate watt rating:</label>
<input type="number" id="user_provided_infloor_heat_wattage" name="user_provided_infloor_heat_wattage" style="display:none;">



   
    <br>

    <input type="submit" name="submit" value="Calculate">
    


<input type="submit" name="reset" value="Reset" formnovalidate></form>













    <?php

// The HTML form and display results sections can then follow as previously described.
// Display feedback message after form submission
if($message) {
    echo "<p>" . $message . "</p>" ;
}


echo "<p>Debug Output:<br>" . $output . "</p>";
//echo $output; // Display all collected messages


    
    return ob_get_clean(); // End output buffering and return everything
}

add_shortcode('load_calculator', 'load_calculator_form_shortcode'); 