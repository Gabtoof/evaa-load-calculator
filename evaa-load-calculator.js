document.addEventListener('DOMContentLoaded', function() {
    var heatingDropdown = document.getElementById('heating');
    if (heatingDropdown) {
        heatingDropdown.addEventListener('change', function() {
            var wattageInput = document.getElementById('provided_heating_wattage');
            if (this.value === 'heating_watt') {
                wattageInput.style.display = 'inline-block';
            } else {
                wattageInput.style.display = 'none';
            }
        });
    }

    

    var waterHeaterDropdown = document.getElementById('water_heater');
    if (waterHeaterDropdown) {
        waterHeaterDropdown.addEventListener('change', function() {
            var wattageInput = document.getElementById('user_provided_water_heater_wattage');
            if (this.value === 'user_provided_water_heater_wattage') {
                wattageInput.style.display = 'inline-block';
            } else {
                wattageInput.style.display = 'none';
            }
        });
    }
    // Added listener for Clothes Dryer dropdown
    var clothesDryerDropdown = document.getElementById('clothes_dryer');
    if (clothesDryerDropdown) {
        clothesDryerDropdown.addEventListener('change', function() {
            var wattageInput = document.getElementById('provided_clothes_dryer_wattage');
            if (this.value === 'provided_clothes_dryer_wattage') {
                wattageInput.style.display = 'inline-block';
            } else {
                wattageInput.style.display = 'none';
            }
        });
    }
    // Added listener for Stove dropdown
    var stoveDropdown = document.getElementById('stove');
    if (stoveDropdown) {
        stoveDropdown.addEventListener('change', function() {
            var wattageInput = document.getElementById('provided_stove_wattage');
            if (this.value === 'provided_stove_wattage') {
                wattageInput.style.display = 'inline-block';
            } else {
                wattageInput.style.display = 'none';
            }
        });
    }
    function setupCheckboxListener(checkboxId, labelId, inputId) {
        var checkbox = document.getElementById(checkboxId);
        var input = document.getElementById(inputId);
        var label = document.getElementById(labelId);

        if (checkbox) {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    input.style.display = 'inline-block';
                    label.style.display = 'inline-block';
                } else {
                    input.style.display = 'none';
                    label.style.display = 'none';
                }
            });
        }
    }

    setupCheckboxListener('ac_checkbox', 'ac_wattage_label', 'ac_wattage');
    setupCheckboxListener('dishwasher_checkbox', 'dishwasher_wattage_label', 'dishwasher_wattage');
    setupCheckboxListener('hottub_checkbox', 'hottub_wattage_label', 'hottub_wattage');
    setupCheckboxListener('infloor_heat_checkbox', 'infloor_heat_wattage_label', 'infloor_heat_wattage');
    // Add other checkbox listeners here as needed...
});
