document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('motorlan-filters-form');
    const resultsContainer = document.getElementById('motorlan-results');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        resultsContainer.innerHTML = '<p>Loading...</p>';

        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value) {
                params.append(key, value);
            }
        }

        // The REST API URL is passed from WordPress using wp_localize_script
        fetch(`${motorlan_filters_vars.api_url}?${params.toString()}`)
            .then(response => response.json())
            .then(motors => {
                displayMotors(motors);
            })
            .catch(error => {
                console.error('Error fetching motors:', error);
                resultsContainer.innerHTML = '<p>Error fetching motors. Please try again.</p>';
            });
    });

    function displayMotors(motors) {
        if (motors.length === 0) {
            resultsContainer.innerHTML = '<p>No motors found.</p>';
            return;
        }

        let html = '<table class="wp-list-table widefat striped"><thead><tr>';
        html += '<th scope="col">Image</th>';
        html += '<th scope="col">Title</th>';
        html += '<th scope="col">Brand</th>';
        html += '<th scope="col">Power (kW)</th>';
        html += '<th scope="col">Speed (rpm)</th>';
        html += '</tr></thead><tbody>';

        motors.forEach(motor => {
            html += '<tr>';
            const imageUrl = motor.acf.motor_image ? motor.acf.motor_image.sizes.thumbnail : '';
            html += `<td>${imageUrl ? `<img src="${imageUrl}" width="100">` : 'No Image'}</td>`;
            html += `<td>${motor.title}</td>`;
            html += `<td>${motor.acf.marca || ''}</td>`;
            html += `<td>${motor.acf.potencia || ''}</td>`;
            html += `<td>${motor.acf.velocidad || ''}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table>';
        resultsContainer.innerHTML = html;
    }
});
