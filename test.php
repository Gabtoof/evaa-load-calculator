// ... [earlier code]

// Convert home size to m² and sqft
echo "Original Home Size: " . $home_size . " " . $home_size_unit . "<br>";

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

// ... [the rest of the code]

// Calculate remaining capacity
$remaining_capacity = $panel_capacity - $total_load;

echo "Panel Capacity: " . $panel_capacity . "W<br>";
echo "Total Load: " . $total_load . "W<br>";
echo "Remaining Capacity: " . $remaining_capacity . "W<br>";

// ... [the rest of the form and code]
