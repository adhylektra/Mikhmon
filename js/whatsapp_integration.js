/**
 * WhatsApp Integration for MikhMon
 * Add WhatsApp notification option to user generation
 */

// Add WhatsApp field to generate user form
function addWhatsAppField() {
    const generateForm = document.querySelector('form[action*="generate"]');
    if (!generateForm) return;
    
    // Find the submit button
    const submitBtn = generateForm.querySelector('input[type="submit"], button[type="submit"]');
    if (!submitBtn) return;
    
    // Create WhatsApp field container
    const waContainer = document.createElement('tr');
    waContainer.innerHTML = `
        <td align="right">
            <input type="checkbox" id="send_wa" name="send_wa" onchange="toggleWhatsAppField()">
            <label for="send_wa">Kirim ke WhatsApp</label>
        </td>
        <td>
            <input type="text" id="wa_number" name="wa_number" placeholder="08123456789" style="display:none;">
            <small id="wa_help" style="display:none; color: #666;">
                Format: 08xxx atau 62xxx
            </small>
        </td>
    `;
    
    // Insert before submit button row
    const submitRow = submitBtn.closest('tr');
    if (submitRow) {
        submitRow.parentNode.insertBefore(waContainer, submitRow);
    }
}

// Toggle WhatsApp number field
function toggleWhatsAppField() {
    const checkbox = document.getElementById('send_wa');
    const numberField = document.getElementById('wa_number');
    const helpText = document.getElementById('wa_help');
    
    if (checkbox && numberField && helpText) {
        if (checkbox.checked) {
            numberField.style.display = 'block';
            numberField.required = true;
            helpText.style.display = 'block';
        } else {
            numberField.style.display = 'none';
            numberField.required = false;
            helpText.style.display = 'none';
        }
    }
}

// Add WhatsApp field to add single user form
function addWhatsAppFieldSingle() {
    const addUserForm = document.querySelector('form[action*="add-user"]');
    if (!addUserForm) return;
    
    const submitBtn = addUserForm.querySelector('input[type="submit"], button[type="submit"]');
    if (!submitBtn) return;
    
    const waContainer = document.createElement('tr');
    waContainer.innerHTML = `
        <td align="right">
            <input type="checkbox" id="send_wa_single" name="send_wa" onchange="toggleWhatsAppFieldSingle()">
            <label for="send_wa_single">Kirim ke WhatsApp</label>
        </td>
        <td>
            <input type="text" id="wa_number_single" name="wa_number" placeholder="08123456789" style="display:none;">
            <small id="wa_help_single" style="display:none; color: #666;">
                Format: 08xxx atau 62xxx
            </small>
        </td>
    `;
    
    const submitRow = submitBtn.closest('tr');
    if (submitRow) {
        submitRow.parentNode.insertBefore(waContainer, submitRow);
    }
}

// Toggle WhatsApp field for single user
function toggleWhatsAppFieldSingle() {
    const checkbox = document.getElementById('send_wa_single');
    const numberField = document.getElementById('wa_number_single');
    const helpText = document.getElementById('wa_help_single');
    
    if (checkbox && numberField && helpText) {
        if (checkbox.checked) {
            numberField.style.display = 'block';
            numberField.required = true;
            helpText.style.display = 'block';
        } else {
            numberField.style.display = 'none';
            numberField.required = false;
            helpText.style.display = 'none';
        }
    }
}

// Send voucher via WhatsApp
function sendVoucherWhatsApp(phone, userData) {
    return fetch('../hotspot/send_voucher_wa.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(userData)
    })
    .then(response => response.json())
    .catch(error => {
        console.error('Error sending WhatsApp:', error);
        return { success: false, message: error.message };
    });
}

// Add WhatsApp button to user list
function addWhatsAppButtonToUserList() {
    const userRows = document.querySelectorAll('table#dataTable tbody tr');
    
    userRows.forEach(row => {
        const actionCell = row.querySelector('td:last-child');
        if (!actionCell) return;
        
        // Get user data from row
        const username = row.querySelector('td:nth-child(2)')?.textContent.trim();
        if (!username) return;
        
        // Create WhatsApp button
        const waBtn = document.createElement('a');
        waBtn.href = '#';
        waBtn.className = 'btn-wa';
        waBtn.innerHTML = '📱';
        waBtn.title = 'Kirim ke WhatsApp';
        waBtn.style.cssText = 'margin-left: 5px; text-decoration: none; font-size: 16px;';
        
        waBtn.onclick = function(e) {
            e.preventDefault();
            const phone = prompt('Masukkan nomor WhatsApp:', '08');
            if (phone) {
                sendUserVoucherWhatsApp(username, phone);
            }
        };
        
        actionCell.appendChild(waBtn);
    });
}

// Send existing user voucher via WhatsApp
function sendUserVoucherWhatsApp(username, phone) {
    // Show loading
    const loading = document.createElement('div');
    loading.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.3); z-index: 9999;';
    loading.innerHTML = '<p>Mengirim voucher ke WhatsApp...</p>';
    document.body.appendChild(loading);
    
    // Get user details and send
    fetch(`../hotspot/get_user_details.php?username=${encodeURIComponent(username)}`)
        .then(response => response.json())
        .then(userData => {
            userData.phone = phone;
            return sendVoucherWhatsApp(phone, userData);
        })
        .then(result => {
            document.body.removeChild(loading);
            if (result.success) {
                alert('✅ Voucher berhasil dikirim ke WhatsApp!');
            } else {
                alert('❌ Gagal mengirim voucher: ' + result.message);
            }
        })
        .catch(error => {
            document.body.removeChild(loading);
            alert('❌ Error: ' + error.message);
        });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check current page and add appropriate fields
    const currentUrl = window.location.href;
    
    if (currentUrl.includes('generate')) {
        addWhatsAppField();
    } else if (currentUrl.includes('add-user')) {
        addWhatsAppFieldSingle();
    } else if (currentUrl.includes('users')) {
        addWhatsAppButtonToUserList();
    }
});

// Export functions for global use
window.toggleWhatsAppField = toggleWhatsAppField;
window.toggleWhatsAppFieldSingle = toggleWhatsAppFieldSingle;
window.sendVoucherWhatsApp = sendVoucherWhatsApp;
