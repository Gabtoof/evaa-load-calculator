console.log('JavaScript file is loaded');

// Define toggleInputDisplay function globally
function toggleInputDisplay(inputId, shouldBeVisible) {
    var input = document.getElementById(inputId);
    var label = document.querySelector('label[for="' + inputId + '"]');
    if (input && label) {
        input.style.display = shouldBeVisible ? 'block' : 'none';
        label.style.display = shouldBeVisible ? 'block' : 'none';
    }
}

// Function to toggle input visibility for a specific dropdown
function toggleDropdownInputVisibility(dropdownId) {
    var dropdown = document.getElementById(dropdownId);
    if (dropdown) {
        var inputId = 'user_provided_' + dropdownId + '_wattage';
        var shouldBeVisible = dropdown.value === dropdownId + '_wattage';
        toggleInputDisplay(inputId, shouldBeVisible);
    }
}

function setupDropdownListener(dropdownId) {
    var dropdown = document.getElementById(dropdownId);
    if (dropdown) {
        dropdown.addEventListener('change', function() {
            console.log('Selected value: ', this.value);
            toggleDropdownInputVisibility(dropdownId);
        });
    }
}

function setupRadioListeners(radioName) {
    var radios = document.querySelectorAll('input[name="' + radioName + '"]');
    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            var inputId = 'user_provided_' + radioName + '_wattage';
            var shouldBeVisible = radio.value === 'yes';
            toggleInputDisplay(inputId, shouldBeVisible);
        });
    });
}

// Function to toggle input visibility for a specific toggle
function setupToggleListener(toggleId, targetInputId) {
    var toggle = document.getElementById(toggleId);
    if (toggle) {
        toggle.addEventListener('change', function() {
            toggleInputDisplay(targetInputId, toggle.checked);
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Setup listeners for dropdown menus
    ['heating', 'water_heater', 'clothes_dryer', 'stove'].forEach(function(feature) {
        console.log('Calling setupDropdownListener for ' + feature);
        setupDropdownListener(feature);
    });

    // Setup listeners for radio button groups
    // ['ac', 'dishwasher', 'hottub', 'infloor_heat'].forEach(function(feature) {
    ['ac', 'hottub', 'infloor_heat'].forEach(function(feature) {
        setupRadioListeners(feature);
        // Initial visibility setup based on the selected radio button
        var isChecked = document.querySelector('input[name="' + feature + '"]:checked').value === 'yes';
        toggleInputDisplay('user_provided_' + feature + '_wattage', isChecked);
    });

    // Setup listeners for toggle switches
    ['toggle1', 'toggle2', 'toggle3'].forEach(function(toggleId) {
        setupToggleListener(toggleId, 'user_provided_' + toggleId + '_wattage');
    });
});

// JavaScript function to set the selected heating option
function selectHeating(option) {
    document.getElementById('heating').value = option;
}



function selectWaterHeater(option) {
    document.getElementById('water_heater').value = option;
    
    // Show/hide the input field for user-provided wattage based on the selected option
    if (option === 'water_heater_wattage') {
        document.getElementById('user_provided_water_heater_wattage_label').style.display = 'block';
        document.getElementById('user_provided_water_heater_wattage').style.display = 'block';
    } else {
        document.getElementById('user_provided_water_heater_wattage_label').style.display = 'none';
        document.getElementById('user_provided_water_heater_wattage').style.display = 'none';
    }
}