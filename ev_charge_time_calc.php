<?php
/*
Plugin Name: EV Charge Time Calculator
Plugin URI: http://example.com/plugin
Description: Calculates the charging time for electric vehicles.
Version: 1.0
Author: Your Name
Author URI: http://example.com
License: GPLv2 or later
Text Domain: ev-charge-time-calculator
*/

// At the beginning of your file, load the EV data
$host = '192.168.99.9:3306';
$username = 'evaa';
$password = 'FiFPpMgXjIU6VyrM1NVGGyCmFF833jkatihtCkCD'; // Replace 'your_password' with the actual password
$database = 'ev_db'; // Replace 'your_database_name' with the name of your database

// Connect to the database
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initial query to fetch all makes
$sql = "SELECT DISTINCT make FROM ev_models";
$result = $conn->query($sql);

// Function to generate dropdown options
function generateOptions($data, $selectedValue = "") {
    $options = "";
    foreach ($data as $row) {
        $selected = ($row == $selectedValue) ? " selected" : "";
        $options .= "<option value='$row'$selected>$row</option>";
    }
    return $options;
}

// Get all makes for initial display
$makes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $makes[] = $row['make'];
    }
}

// Handle user selections (if submitted)
$selectedMake = "";
$selectedModel = "";
if (isset($_POST['make']) && !empty($_POST['make'])) {
    $selectedMake = $_POST['make'];
    $sql = "SELECT DISTINCT model FROM ev_models WHERE make = '$selectedMake'";
    $result = $conn->query($sql);
    $models = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $models[] = $row['model'];
        }
    }
}

if (isset($_POST['model']) && !empty($_POST['model'])) {
    $selectedModel = $_POST['model'];
    // Build query with filters based on selections
    $sql = "SELECT * FROM ev_models WHERE make = '$selectedMake' AND model = '$selectedModel'";
    $result = $conn->query($sql);
}

// Close the connection
$conn->close();

//error_function_does_not_exist();

// Shortcode to display the form
function ev_charge_time_calculator_shortcode() {
    global $evData; // Make sure to use the global variable if needed

    ob_start(); // Start output buffering










    // if (!is_null($evData)) {
    //     echo '<h2>EV Data</h2>';
    //     echo '<ul>';
    //     foreach ($evData as $ev) {
    //         echo '<li>';
    //         echo 'Make: ' . htmlspecialchars($ev['make']) . '<br>';
    //         echo 'Model: ' . htmlspecialchars($ev['model']) . '<br>';
    //         echo 'Year: ' . htmlspecialchars($ev['year']) . '<br>';
    //         echo 'Battery Size: ' . htmlspecialchars($ev['battery_size_kWh']) . ' kWh<br>';
    //         echo 'Range: ' . htmlspecialchars($ev['range_km']) . ' km';
    //         echo '</li>';
    //     }
    //     echo '</ul>';
    // } else {
    //     echo 'No EV data available.';
    // }

    
    ?>
       <form method="post">
        <select name="make">
            <option value="">Select Make</option>
            <?php echo generateOptions($makes, $selectedMake); ?>
        </select>
        <select name="model" disabled>
            <option value="">Select Model</option>
            <?php if (isset($models)): echo generateOptions($models, $selectedModel); endif; ?>
        </select>
        <button type="submit">Filter</button>
    </form>

    <?php if (isset($result) && $result->num_rows > 0): ?>
        <h2>Filtered Results</h2>
        <table>
            <thead>
                <tr>
                    <th>Make</th>
                    <th>Model</th>
                    <th>Year</th>
                    <th>Battery Size (kWh)</th>
                    <th>Range (km)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['make']; ?></td>
                    <td><?php echo $row['model']; ?></td>
                    <td><?php echo $row['year']; ?></td>
                    <td><?php echo $row['battery_size_kWh']; ?></td>
                    <td><?php echo $row['range_km']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <script>
        // Enable/disable model selection based on make selection
        document.getElementById('make').addEventListener('change', function() {
            document.getElementById('model').disabled = (this.value === "");
        });
    </script>
    <?php

    return ob_get_clean(); // Return the output buffer contents
}

// Register the shortcode
add_shortcode('ev_charge_time_calculator', 'ev_charge_time_calculator_shortcode');
