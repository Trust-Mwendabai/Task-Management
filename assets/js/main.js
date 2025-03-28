document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const taskForm = document.getElementById('taskForm');
    if (taskForm) {
        taskForm.addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const deadline = document.getElementById('deadline').value;
            
            if (!title) {
                e.preventDefault();
                alert('Please enter a task title');
                return false;
            }
            
            if (!deadline) {
                e.preventDefault();
                alert('Please select a deadline');
                return false;
            }
        });
    }
    
    // Task completion toggle
    document.querySelectorAll('.toggle-status').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const taskId = this.dataset.taskId;
            const status = this.dataset.status;
            
            fetch('update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `task_id=${taskId}&status=${status === 'pending' ? 'completed' : 'pending'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating task status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating task status');
            });
        });
    });
    
    // Delete task confirmation
    document.querySelectorAll('.delete-task').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this task?')) {
                e.preventDefault();
            }
        });
    });
    
    // Deadline validation
    const deadlineInput = document.getElementById('deadline');
    if (deadlineInput) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        deadlineInput.setAttribute('min', today.toISOString().split('T')[0]);
    }
});
