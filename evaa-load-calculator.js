console.log('JavaScript file is loaded');

// Define toggleInputDisplay function globally
// Adjust the toggleInputDisplay function to also consider labels
// function toggleInputDisplay(inputId, shouldBeVisible) {
//   var input = document.getElementById(inputId);
//   var label = document.querySelector('label[for="' + inputId + '"]');
//   if (input) {
//     input.style.display = shouldBeVisible ? 'block' : 'none';
//   }
//   if (label) {
//     label.style.display = shouldBeVisible ? 'block' : 'none';
//   }
// }

// // Ensure toggleDropdownInputVisibility and setupDropdownListener functions are correctly defined to handle toggling
// function toggleDropdownInputVisibility(dropdownId) {
//   var dropdown = document.getElementById(dropdownId);
//   if (dropdown) {
//     var shouldBeVisible = dropdown.value === 'clothes_dryer_wattage';
//     toggleInputDisplay('user_provided_clothes_dryer_wattage', shouldBeVisible);
//     toggleInputDisplay('user_provided_clothes_dryer_wattage_label', shouldBeVisible); // Ensure label visibility is toggled too
//   }
// }

// function setupDropdownListener(dropdownId) {
//   var dropdown = document.getElementById(dropdownId);
//   if (dropdown) {
//     dropdown.addEventListener('change', function() {
//       console.log('Selected value: ', this.value);
//       toggleDropdownInputVisibility(dropdownId);
//     });
//   }
// }

// function setupRadioListeners(radioName) {
//   var radios = document.querySelectorAll('input[name="' + radioName + '"]');
//   radios.forEach(function(radio) {
//     radio.addEventListener('change', function() {
//       var inputId = 'user_provided_' + radioName + '_wattage';
//       var shouldBeVisible = radio.value === 'yes';
//       toggleInputDisplay(inputId, shouldBeVisible);
//     });
//   });
// }

// function setupToggleListener(toggleId, targetInputId) {
//   var toggle = document.getElementById(toggleId);
//   if (toggle) {
//     toggle.addEventListener('change', function() {
//       toggleInputDisplay(targetInputId, toggle.checked);
//     });
//   }
// }

// document.addEventListener('DOMContentLoaded', function() {

//   // **Clothes dryer dropdown handling:**
//   // Explicit check for the clothes_dryer dropdown to ensure correct visibility of the wattage input on page load
//   var clothesDryerDropdown = document.getElementById('clothes_dryer');
//   if (clothesDryerDropdown && clothesDryerDropdown.value === 'clothes_dryer_wattage') {
//     toggleInputDisplay('user_provided_clothes_dryer_wattage', true);
//   } else {
//     toggleInputDisplay('user_provided_clothes_dryer_wattage', false);
//   }

  // Setup listeners for dropdown menus and ensure correct initial state
//   ['heating', 'water_heater', 'stove'].forEach(function(feature) {
//     console.log('Calling setupDropdownListener for ' + feature);
//     setupDropdownListener(feature);
//     // Ensure correct initial visibility state for each feature
//     toggleDropdownInputVisibility(feature);
//   });

  // Setup listeners for radio button groups
//   ['ac', 'hottub', 'infloor_heat'].forEach(function(feature) {
//     setupRadioListeners(feature);
//     // Initial visibility setup based on the selected radio button
//     var isChecked = document.querySelector('input[name="' + feature + '"]:checked').value === 'yes';
//     toggleInputDisplay('user_provided_' + feature + '_wattage', isChecked);
//   });

//   // Setup listeners for toggle switches
//   ['toggle1', 'toggle2', 'toggle3'].forEach(function(toggleId) {
//     setupToggleListener(toggleId, 'user_provided_' + toggleId + '_wattage');
//   });
// });
