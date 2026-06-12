function editWanted(data) {
    document.getElementById('form-id').value = data.id;
    document.getElementById('form-name').value = data.name;
    document.getElementById('form-epithet').value = data.epithet;
    document.getElementById('form-bounty').value = data.bounty;
    document.getElementById('form-image_url').value = data.image_url;
    document.getElementById('form-reason').value = data.reason;
    if (document.getElementById('form-entity_id')) {
        document.getElementById('form-entity_id').value = data.entity_id || '0';
    }
}
function resetForm() {
    document.getElementById('form-id').value = '0';
    document.getElementById('form-name').value = '';
    document.getElementById('form-epithet').value = '';
    document.getElementById('form-bounty').value = '';
    document.getElementById('form-image_url').value = '';
    document.getElementById('form-reason').value = '';
    if (document.getElementById('form-entity_id')) {
        document.getElementById('form-entity_id').value = '0';
    }
}
