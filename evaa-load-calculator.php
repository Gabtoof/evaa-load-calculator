<?php
/*
Plugin Name: EVAA Load Calculator
Description: A plugin to calculate the electrical load for adding an EV charger.
Version: 1.0
Author: Andrew Baituk
*/

// Enqueue the JS script
function evaa_load_calculator_scripts() {
    wp_enqueue_script('evaa-calculator', plugin_dir_url(__FILE__) . 'evaa-load-calculator.js', array('jquery'), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'evaa_load_calculator_scripts');


//short code
function load_calculator_form_shortcode() {
    ob_start(); // Start output buffering




// Initialize variables with default values
$home_size_unit = "sqft"; // Replace 'default_unit' with whatever default value you deem appropriate
$home_size = 0; // Default to 0, or any other appropriate value

// Check if the keys exist in the $_POST data and assign them
if (isset($_POST['home_size_unit'])) {
    $home_size_unit = $_POST['home_size_unit'];
}

if (isset($_POST['home_size'])) {
    $home_size = intval($_POST['home_size']); // Convert to integer for safety
}


$total_load = 0;
$message = "";

// If the form has been submitted
if(isset($_POST['submit'])) {

    // Service Panel Capacity in Amps
$panel_capacity_amps = intval($_POST['panel_capacity_amps']);

// Convert to Watts (assuming 240V)
$panel_capacity = $panel_capacity_amps * 240;

echo "Original Home Size: " . $home_size . " " . $home_size_unit . "<br>";

// Convert home size to m² and sqft
$home_size_m2 = ($home_size_unit == "sqft") ? $home_size * 0.092903 : $home_size;
$home_size_sqft = ($home_size_unit == "m2") ? $home_size * 10.764 : $home_size;

echo "Converted Home Size in m2: " . $home_size_m2 . " m2<br>";
echo "Converted Home Size in sqft: " . $home_size_sqft . " sqft<br>";

// Living Area load calculation based on m²
if($home_size_m2 <= 90) {
$total_load += 5000;
} else {
$total_load += 5000 + (1000 * ceil(($home_size_m2 - 90)/90));
}
  
echo "Base Living Area Load: 5000W<br>";
echo "Additional Living Area Load: " . ($total_load - 5000) . "W<br>";
echo "Total Living Area Load: " . $total_load . "W<br>";

// Before heating calculation
echo "Load before heating calculation: " . $total_load . "W<br>";
// Heating
$heating_type = $_POST['heating'];
switch($heating_type) {
    case "gas":
        $total_load += 960; // 960W for gas furnace
        break;
    case "electric":
        // Determine load based on home size_sqft
        if($home_size_sqft <= 1500) {
            $total_load += 10000; // 10kW
        } elseif($home_size_sqft > 1500 && $home_size_sqft <= 3000) {
            $total_load += 15000; // 15kW
        } else {
            $total_load += 20000; // 20kW
        }
        break;
    case "air_heat_pump":
        // Determine load based on home size_sqft
        if($home_size_sqft <= 1500) {
            $total_load += 3500; // 3.5kW
        } elseif($home_size_sqft > 1500 && $home_size_sqft <= 3000) {
            $total_load += 5000; // 5kW
        } else {
            $total_load += 7000; // 7kW
        }
        break;
    case "geo_heat_pump":
        // Determine load based on home size_sqft
        if($home_size_sqft <= 1500) {
            $total_load += 5000; // 5kW
        } elseif($home_size_sqft > 1500 && $home_size_sqft <= 3000) {
            $total_load += 7000; // 7kW
        } else {
            $total_load += 9000; // 9kW
        }
        break;
    case "boiler":
        // Determine load based on home size_sqft
        if($home_size_sqft <= 1500) {
            $total_load += 8000; // 8kW
        } elseif($home_size_sqft > 1500 && $home_size_sqft <= 3000) {
            $total_load += 12000; // 12kW
        } else {
            $total_load += 15000; // 15kW
        }
        break;
    case "heating_watt":
        $total_load += intval($_POST['user_provided_heating_wattage']);
        break;

        
}
// After heating calculation
echo "Load after heating calculation: " . $total_load . "W<br>";

echo "Load before AC calculation: " . $total_load . "W<br>";


// AC calculation
if(isset($_POST['ac']) && $_POST['ac'] === 'yes') {
    // If AC wattage is provided by user, use that; otherwise, use default
    if (isset($_POST['user_provided_ac_wattage']) && !empty($_POST['user_provided_ac_wattage'])) {
        $ac_wattage = intval($_POST['user_provided_ac_wattage']);
    } else {
        $ac_wattage = 3500; // Default AC wattage
    }
    $total_load += $ac_wattage;
}

echo "Load after AC calculation: " . $total_load . "W<br>";

// dishwasher
echo "Load before Dishwasher calculation: " . $total_load . "W<br>";
if(isset($_POST['dishwasher'])) {
    if (isset($_POST['user_provided_dishwasher_wattage']) && !empty($_POST['user_provided_dishwasher_wattage'])) {
        $dishwasher_wattage = intval($_POST['user_provided_dishwasher_wattage']);
    } else {
        $dishwasher_wattage = 1800; // default value
    }
    $total_load += $dishwasher_wattage;
}
echo "Load after Dishwasher calculation: " . $total_load . "W<br>";
echo "Load before Hot Tub calculation: " . $total_load . "W<br>";
// hottub
if(isset($_POST['hottub'])) {
    if (isset($_POST['user_provided_hottub_wattage']) && !empty($_POST['user_provided_hottub_wattage'])) {
        $hottub_wattage = intval($_POST['user_provided_hottub_wattage']);
    } else {
        $hottub_wattage = 12000; // default value
    }
    $total_load += $hottub_wattage;
}
echo "Load after Hot Tub calculation: " . $total_load . "W<br>";
echo "Load before Infloor Heating calculation: " . $total_load . "W<br>";
// infloor_heat
if(isset($_POST['infloor_heat'])) {
    if (isset($_POST['user_provided_infloor_heat_wattage']) && !empty($_POST['user_provided_infloor_heat_wattage'])) {
        $infloor_heat_wattage = intval($_POST['user_provided_infloor_heat_wattage']);
    } else {
        $infloor_heat_wattage = 1800; // default value
    }
    $total_load += $infloor_heat_wattage;
}
echo "Load after Infloor Heating calculation: " . $total_load . "W<br>";



echo "Load before Water Heater calculation: " . $total_load . "W<br>";

// Water Heater
$water_heater_type = $_POST['water_heater'];

switch($water_heater_type) {
    case "electric":
        $total_load += 4500; // Default wattage for electric water heater
        break;
    case "gas":
        $total_load += 600; // Default wattage for gas water heater
        break;
    case "tankless":
        $total_load += 12000; // Default wattage for tankless water heater
        break;
    case "user_provided_water_heater_wattage": // This case should match the value attribute from the HTML select option
        // Check if the user provided a custom wattage
        if (isset($_POST['user_provided_water_heater_wattage']) && !empty($_POST['user_provided_water_heater_wattage'])) {
            // Convert and add the user-entered wattage to the total load
            $user_provided_wattage = intval($_POST['user_provided_water_heater_wattage']); // Make sure this variable name is consistent
            $total_load += $user_provided_wattage;
        }
        break;
    default:
        // Optionally handle unexpected cases
        break;
}

echo "Load after Water Heater calculation: " . $total_load . "W<br>";

echo "Load before Clothes Dryer calculation: " . $total_load . "W<br>";

// Clothes Dryer
$clothes_dryer_type = $_POST['clothes_dryer'];

switch($clothes_dryer_type) {
    case "electric":
        $total_load += 6000; // Default wattage for electric clothes dryer
        break;
    case "gas":
        $total_load += 1200; // Default wattage for gas clothes dryer
        break;
    case "user_provided_clothes_dryer_wattage":
        if (isset($_POST['user_provided_clothes_dryer_wattage']) && !empty($_POST['user_provided_clothes_dryer_wattage'])) {
            $total_load += intval($_POST['user_provided_clothes_dryer_wattage']);
        }
        break;
    // Add cases for other types if necessary
}

echo "Load after Clothes Dryer calculation: " . $total_load . "W<br>";

echo "Load before Stove calculation: " . $total_load . "W<br>";

// Stove
$stove_type = $_POST['stove'];

switch($stove_type) {
    case "electric":
        $total_load += 5000; // Default wattage for electric stove
        break;
    case "user_provided_stove_wattage":
        if (isset($_POST['user_provided_stove_wattage']) && !empty($_POST['user_provided_stove_wattage'])) {
            $total_load += intval($_POST['user_provided_stove_wattage']);
        }
        break;
    // Add cases for other types if necessary
}

echo "Load after Stove calculation: " . $total_load . "W<br>";


    // Other appliances and features...
    // ...

    // Calculate remaining capacity
    $remaining_capacity = $panel_capacity - $total_load;

echo "Panel Capacity: " . $panel_capacity . "W<br>";
echo "Total Load: " . $total_load . "W<br>";
echo "Remaining Capacity: " . $remaining_capacity . "W<br>";

    // EV Charger Load (placeholder value)
    $ev_charger_load = 7000; // Replace with the typical load of the EV charger you're considering

if($remaining_capacity >= $ev_charger_load) {
    $message = "You have enough capacity to add an EV charger! Your remaining capacity is " . $remaining_capacity . "W, and the typical EV charger requires about " . $ev_charger_load . "W.";
} else {
    $message = "Based on the provided details, you might need to upgrade your panel to add an EV charger. Your remaining capacity is " . $remaining_capacity . "W, but a typical EV charger requires about " . $ev_charger_load . "W.";
}


}

// The HTML form and display results sections can then follow as previously described.
// Display feedback message after form submission
if($message) {
    echo "<p>" . $message . "</p>";
}

// Your HTML form
?>
<form action="" method="post">
   
    <label for="panel_capacity_amps">Panel Capacity (in Amps):</label>
    <input type="number" name="panel_capacity_amps" required><br>

    <label for="home_size">Approx size of home (developed/livable area):</label>
    <input type="number" name="home_size" required>
    <select name="home_size_unit">
        <option value="sqft">sq ft</option>
        <option value="m2">m²</option>
    </select>
    <br>

    <label for="heating">Heating Type:</label>
    <select name="heating" id="heating">
        <option value="gas">Gas Furnace</option>
        <option value="electric">Electric Furnace (est based on home size)  </option>
        <option value="air_heat_pump">Air Source Heat Pump</option>
        <option value="geo_heat_pump">Geothermal Heat Pump</option>
        <option value="boiler">Boiler System</option>
        <option value="heating_watt">I'll provide my heating wattage:</option>
        <!-- Input field for heating wattage -->
        <input type="number" name="user_provided_heating_wattage" id="user_provided__heating_wattage" style="display: none;">
    </select>
    <br>

    <label for="water_heater">Water Heater Type:</label>
<select name="water_heater" id="water_heater">
    <option value="gas">Gas</option>
    <option value="electric">Electric</option>
    <option value="tankless">Tankless</option>
    <option value="water_heater_wattage">I'll provide my water heater wattage:</option>
</select>
<!-- Input field for user-entered wattage -->
<input type="number" name="user_provided_water_heater_wattage" id="user_provided_water_heater_wattage" style="display: none;">
<br>

<label for="clothes_dryer">Clothes Dryer Type:</label>
<select name="clothes_dryer" id="clothes_dryer">
    <option value="gas">Gas</option>
    <option value="electric">Electric</option>
    <option value="clothes_dryer_wattage">I'll provide my clothes dryer wattage:</option>
</select>
<!-- Input field for user-provided clothes dryer wattage -->
<input type="number" name="user_provided_clothes_dryer_wattage" id="user_provided_clothes_dryer_wattage" style="display: none;"><br>

<label for="stove">Stove:</label>
<select name="stove" id="stove">
    <option value="gas">Gas</option>
    <option value="electric">Electric</option>
    <option value="stove_wattage">I'll provide my stove wattage:</option>
</select>
<!-- Input field for user-provided stove wattage -->
<input type="number" name="user_provided_stove_wattage" id="user_provided_stove_wattage" style="display: none;"><br>

<br>

<label>Do you have Air Conditioning?</label>
<input type="radio" id="ac_yes" name="ac" value="yes" onclick="toggleACInput(true)">
<label for="ac_yes">Yes</label>
<input type="radio" id="ac_no" name="ac" value="no" onclick="toggleACInput(false)" checked>
<label for="ac_no">No</label><br>

<label for="user_provided_ac_wattage" id="user_provided_ac_wattage_label" style="display:none;">AC Wattage (if known):</label>
<input type="number" id="user_provided_ac_wattage" name="user_provided_ac_wattage" style="display:none;"><br>

<script type="text/javascript">
    function toggleACInput(show) {
        document.getElementById('ac_wattage_label').style.display = show ? 'inline-block' : 'none';
        document.getElementById('ac_wattage').style.display = show ? 'inline-block' : 'none';
    }
</script>



    <label for="dishwasher">Do you have a dishwasher?</label>
    <input type="checkbox" id="dishwasher_checkbox" name="dishwasher" value="yes"> Yes<br>
    <label for="dishwasher_wattage" id="dishwasher_wattage_label" style="display:none;">Dishwasher Wattage (if known) <otherwise using 1800W>:</label>
    <input type="number" id="user_provided_dishwasher_wattage" name="user_provided_dishwasher_wattage" style="display:none;"><br>

    <label for="hottub">Do you have a hottub?</label>
    <input type="checkbox" id="hottub_checkbox" name="hottub" value="yes"> Yes<br>
    <label for="hottub_wattage" id="hottub_wattage_label" style="display:none;">Hottub Wattage (if known) <otherwise using 12000W>:</label>
    <input type="number" id="user_provided_hottub_wattage" name="user_provided_hottub_wattage" style="display:none;"><br>

    <label for="infloor_heat">Do you have electric infloor heating?</label>
    <input type="checkbox" id="infloor_heat_checkbox" name="infloor_heat" value="yes"> Yes<br>
    <label for="infloor_heat_wattage" id="infloor_heat_wattage_label" style="display:none;">Infloor Heating Wattage (if known) <otherwise using 1800W>:</label>
    <input type="number" id="user_provided_infloor_heat_wattage" name="user_provided_infloor_heat_wattage" style="display:none;"><br>

   
    <br>

    <!-- Any additional form fields can be added here... -->

    <input type="submit" name="submit" value="Calculate">
</form>

    <?php
    
    return ob_get_clean(); // End output buffering and return everything
}
add_shortcode('load_calculator', 'load_calculator_form_shortcode');