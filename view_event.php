<?php
require_once 'config.php';

$shareId = $_GET['id'] ?? '';
$event = null;

if ($shareId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE share_link = ?");
        $stmt->execute([$shareId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($event) {
            $stmt = $pdo->prepare("SELECT * FROM date_preferences WHERE event_id = ? ORDER BY created_at DESC");
            $stmt->execute([$event['id']]);
            $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = 'Database error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Event</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <div class="container">
        <?php if ($event): ?>
            <h2>Event Details</h2>
            <div class="event-details">
                <p><strong>Event Name:</strong> <?php echo htmlspecialchars($event['event_name']); ?></p>
                <p><strong>Time Required:</strong> <?php echo htmlspecialchars($event['time_required']); ?></p>
                <p><strong>Created:</strong> <?php echo date('F j, Y, g:i a', strtotime($event['created_at'])); ?></p>
            </div>

            <div class="preference-section">
                <h3>Add Your Availability</h3>
                <form id="preferenceForm">
                    <input type="hidden" id="eventId" value="<?php echo $event['id']; ?>">
                    
                    <div class="form-group">
                        <label for="startDateTime">Start Date and Time</label>
                        <input type="text" id="startDateTime" placeholder="Select start date and time" required>
                    </div>

                    <div class="form-group">
                        <label for="endDateTime">End Date and Time</label>
                        <input type="text" id="endDateTime" placeholder="Select end date and time" required>
                    </div>

                    <div class="form-group">
                        <label for="preferenceScore">Preference (1-10)</label>
                        <input type="range" id="preferenceScore" min="1" max="10" value="5" 
                               oninput="updatePreferenceColor(this.value)">
                        <span id="preferenceValue">5</span>
                    </div>

                    <button type="submit">Add Preference</button>
                </form>
            </div>

            <div class="existing-preferences">
                <h3>Existing Preferences</h3>
                <div id="preferencesContainer">
                    <?php foreach ($preferences as $pref): ?>
                        <div class="preference-item" 
                             style="background-color: <?php echo getPreferenceColor($pref['preference_score']); ?>">
                            <div class="preference-details">
                                <div class="dates">
                                    <?php echo date('M j, Y', strtotime($pref['start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($pref['end_date'])); ?>
                                </div>
                                <div class="times">
                                    <?php echo date('g:i A', strtotime($pref['start_date'])); ?> - 
                                    <?php echo date('g:i A', strtotime($pref['end_date'])); ?>
                                </div>
                            </div>
                            <div class="preference-right">
                                <span class="score">
                                    Preference: <?php echo $pref['preference_score']; ?>/10
                                </span>
                                <button type="button" 
                                        class="delete-btn"
                                        onclick="deletePreference(<?php echo $pref['id']; ?>)">
                                    ×
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <style>
                .preference-item {
                    padding: 10px;
                    margin: 5px 0;
                    border-radius: 4px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .preference-details {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                .times {
                    font-size: 0.9em;
                    opacity: 0.8;
                }
                .preference-right {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .delete-btn {
                    background: rgba(0, 0, 0, 0.1);
                    border: none;
                    color: #333;
                    font-size: 20px;
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0;
                    line-height: 1;
                }
                .delete-btn:hover {
                    background: rgba(0, 0, 0, 0.2);
                }
                #preferenceScore {
                    width: 100%;
                }
                .preference-section {
                    margin-top: 2rem;
                    padding-top: 2rem;
                    border-top: 1px solid #ddd;
                }
                .flatpickr-day.disabled-date {
                    background-color: var(--preference-color);
                    border-color: transparent;
                    color: white;
                    cursor: not-allowed;
                }
                .flatpickr-day.disabled-date:hover {
                    background-color: var(--preference-color);
                }
                .flatpickr-calendar.hasTime .flatpickr-time {
                    border-top: 1px solid #e6e6e6;
                }
            </style>

            <script>
                // Store existing preferences
                const existingPreferences = <?php echo json_encode($preferences); ?>;
                
                async function deletePreference(preferenceId) {
                    if (!confirm('Are you sure you want to delete this preference?')) {
                        return;
                    }

                    try {
                        const response = await fetch('delete_preference.php', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                preferenceId: preferenceId
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error deleting preference: ' + (data.error || 'Unknown error'));
                        }
                    } catch (error) {
                        alert('Error deleting preference. Please try again.');
                        console.error('Error:', error);
                    }
                }

                function getPreferenceColor(score) {
                    // Convert score 1-10 to hue value (orange=30 to green=120)
                    const hue = 30 + (score - 1) * 10;
                    return `hsl(${hue}, 70%, 70%)`;
                }

                function updatePreferenceColor(value) {
                    document.getElementById('preferenceValue').textContent = value;
                }

                function isDateTimeInRange(date, startDate, endDate) {
                    const checkDate = new Date(date);
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    
                    // Set hours to 0 to compare only dates when checking if date should be disabled
                    const checkDateOnly = new Date(checkDate.getFullYear(), checkDate.getMonth(), checkDate.getDate());
                    const startDateOnly = new Date(start.getFullYear(), start.getMonth(), start.getDate());
                    const endDateOnly = new Date(end.getFullYear(), end.getMonth(), end.getDate());
                    
                    return checkDateOnly >= startDateOnly && checkDateOnly <= endDateOnly;
                }

                // Helper function to format preferences for display
                function formatPreferences() {
                    return existingPreferences.map(pref => ({
                        ...pref,
                        formattedDate: `${pref.start_date.split(' ')[0]}: ${
                            new Date(pref.start_date).toLocaleTimeString('en-US', { 
                                hour: 'numeric', 
                                minute: '2-digit',
                                hour12: true 
                            })} - ${
                            new Date(pref.end_date).toLocaleTimeString('en-US', { 
                                hour: 'numeric', 
                                minute: '2-digit',
                                hour12: true 
                            })}`
                    }));
                }

                // Common configuration for both pickers
                const pickerConfig = {
                    enableTime: true,
                    dateFormat: "Y-m-d H:i",
                    time_24hr: false,
                    minDate: "today",
                    minuteIncrement: 30,
                    onDayCreate: function(dObj, dStr, fp, dayElem) {
                        const currentDate = dayElem.dateObj;
                        const formattedPrefs = formatPreferences();
                        
                        // Check if this date has any preferences
                        const datePrefs = formattedPrefs.filter(pref => 
                            isDateTimeInRange(currentDate, pref.start_date, pref.end_date)
                        );

                        if (datePrefs.length > 0) {
                            dayElem.classList.add('disabled-date');
                            // Set custom property for color based on highest preference
                            const highestPref = Math.max(...datePrefs.map(p => p.preference_score));
                            dayElem.style.setProperty(
                                '--preference-color', 
                                getPreferenceColor(highestPref)
                            );

                            // Add title with all preferences for this date
                            dayElem.title = datePrefs
                                .map(p => p.formattedDate + ` (Preference: ${p.preference_score}/10)`)
                                .join('\n');
                        }
                    }
                };

                // Initialize Flatpickr for start date-time
                const startPicker = flatpickr("#startDateTime", {
                    ...pickerConfig,
                    onChange: function(selectedDates) {
                        if (selectedDates[0]) {
                            endPicker.set('minDate', selectedDates[0]);
                        }
                    }
                });

                // Initialize Flatpickr for end date-time
                const endPicker = flatpickr("#endDateTime", {
                    ...pickerConfig
                });

                // Add hover styles for the tooltips
                const style = document.createElement('style');
                style.textContent = `
                    .flatpickr-day[title] {
                        position: relative;
                    }
                    .flatpickr-day[title]:hover::after {
                        content: attr(title);
                        position: absolute;
                        bottom: 100%;
                        left: 50%;
                        transform: translateX(-50%);
                        background: rgba(0, 0, 0, 0.8);
                        color: white;
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 14px;
                        white-space: pre;
                        z-index: 10;
                        width: max-content;
                    }
                `;
                document.head.appendChild(style);

                document.getElementById('preferenceForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const startDateTime = document.getElementById('startDateTime').value;
                    const endDateTime = document.getElementById('endDateTime').value;
                    
                    if (!startDateTime || !endDateTime) {
                        alert('Please select both start and end date/time');
                        return;
                    }

                    const start = new Date(startDateTime);
                    const end = new Date(endDateTime);

                    if (start >= end) {
                        alert('End date/time must be after start date/time');
                        return;
                    }

                    // Check if selected range overlaps with existing preferences
                    const hasOverlap = existingPreferences.some(pref => {
                        const prefStart = new Date(pref.start_date);
                        const prefEnd = new Date(pref.end_date);
                        
                        return (start <= prefEnd && end >= prefStart);
                    });

                    if (hasOverlap) {
                        alert('Selected date and time range overlaps with existing preferences');
                        return;
                    }

                    const response = await fetch('save_preference.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            eventId: document.getElementById('eventId').value,
                            startDate: startDateTime,
                            endDate: endDateTime,
                            preferenceScore: document.getElementById('preferenceScore').value
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error saving preference');
                    }
                });
            </script>
        <?php else: ?>
            <div class="error">
                <p>Event not found or invalid link.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
function getPreferenceColor($score) {
    // Convert score 1-10 to hue value (orange=30 to green=120)
    $hue = 30 + ($score - 1) * 10;
    return "hsl($hue, 70%, 70%)";
}
?> 