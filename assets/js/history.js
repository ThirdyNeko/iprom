async function loadHistory(employeeId) {
    const container = document.getElementById('historyContainer');

    if (!container) return;

    container.innerHTML = '<div class="text-muted small">Loading...</div>';

    try {
        const res = await fetch(`functions/get_employee_history.php?id=${employeeId}`);
        const data = await res.json();

        if (!data || data.length === 0) {
            container.innerHTML = '<div class="text-muted small">No history available</div>';
            return;
        }

        container.innerHTML = data.map(item => {
            const date = new Date(item.update_date);

            const formatted = date.toLocaleString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });

            return `
                <div class="history-item">
                    <div class="history-reason">
                        ${item.reason_for_update || 'No reason'}
                    </div>

                    ${item.remarks ? `
                        <div class="history-remarks text-muted small">
                            Remarks: ${item.remarks}
                        </div>
                    ` : ''}

                    <div class="history-date">${formatted}</div>
                </div>
            `;
        }).join('');

    } catch (err) {
        console.error(err);
        container.innerHTML = '<div class="text-danger small">Failed to load history</div>';
    }
}