/**
 * event-approval.js - Production version
 *
 * This file handles the front-end functionality for the Event Approval Meta Box.
 * It listens for clicks on the approval buttons and uses AJAX to send the
 * approval request to the server, updating the UI accordingly.
 *
 * @package YourThemeName/Events
 */

// Use a self-executing anonymous function to prevent conflicts.
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Check if jQuery is available
        if (typeof $ === 'undefined') {
            return;
        }
        
        // Check if eventApprovalAjax is properly loaded
        if (typeof eventApprovalAjax === 'undefined') {
            // Try to find approval buttons and show an error
            var $approveButtons = $('.approve-event-button');
            if ($approveButtons.length > 0) {
                $approveButtons.prop('disabled', true)
                    .text('Error: AJAX not configured')
                    .css('background-color', '#dc3545');
            }
            return;
        }
        
        if (!eventApprovalAjax.ajax_url || !eventApprovalAjax.nonce) {
            return;
        }
        
        // Find all buttons with the 'approve-event-button' class.
        var $approveButtons = $('.approve-event-button');
        
        if ($approveButtons.length === 0) {
            return;
        }

        // Bind click event to approval buttons
        $approveButtons.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);
            var postId = parseInt($button.data('post-id'), 10);
            var termId = parseInt($button.data('term-id'), 10);
            var originalButtonText = $button.text();
            
            // Validate data
            if (!postId || !termId || isNaN(postId) || isNaN(termId)) {
                alert('Error: Invalid button data');
                return false;
            }
            
            // Disable the button and show a loading state
            $button.prop('disabled', true).text('Approving...');

            // Prepare the data for the AJAX request
            var data = {
                'action': 'approve_event_calendar',
                'nonce': eventApprovalAjax.nonce,
                'post_id': postId,
                'term_id': termId
            };

            // Send the AJAX request
            $.ajax({
                url: eventApprovalAjax.ajax_url,
                type: 'POST',
                data: data,
                dataType: 'json',
                timeout: 30000
            })
            .done(function (response) {
                if (response && response.success) {
                    // Find the correct status span for this specific calendar
                    // Extract the calendar name from the button text (removes "Approve for " prefix)
                    var calendarName = originalButtonText.replace(/^Approve for\s+/, '');
                    
                    // Find the paragraph that contains this calendar name
                    var $approvalContainer = $('#event-approval-status-container');
                    var $calendarParagraph = null;
                    
                    // Look for the paragraph containing the strong tag with this calendar name
                    $approvalContainer.find('p').each(function() {
                        var $p = $(this);
                        var $strong = $p.find('strong').first();
                        if ($strong.length > 0) {
                            // Remove the colon and compare
                            var paragraphCalendarName = $strong.text().replace(':', '').trim();
                            if (paragraphCalendarName === calendarName) {
                                $calendarParagraph = $p;
                                return false; // Break out of each loop
                            }
                        }
                    });
                    
                    // If we found the correct paragraph, update its status span
                    if ($calendarParagraph && $calendarParagraph.length > 0) {
                        var $statusSpan = $calendarParagraph.find('span').filter(function() {
                            var text = $(this).text();
                            return text.indexOf('Pending') !== -1 || text.indexOf('Not Submitted') !== -1;
                        });
                        
                        if ($statusSpan.length > 0) {
                            $statusSpan.text('Approved').attr('style', 'color: green;');
                        }
                    } else {
                        // Fallback: if we can't find the specific paragraph, 
                        // try to find it by traversing from the button
                        var $calendarParagraph = $button.prevAll('p').first();
                        if ($calendarParagraph.length > 0) {
                            var $statusSpan = $calendarParagraph.find('span').filter(function() {
                                var text = $(this).text();
                                return text.indexOf('Pending') !== -1 || text.indexOf('Not Submitted') !== -1;
                            });
                            
                            if ($statusSpan.length > 0) {
                                $statusSpan.text('Approved').attr('style', 'color: green;');
                            }
                        }
                    }
                    
                    // Remove the approval button
                    $button.remove();
                    
                    // Show success message
                    if (!$('#approval-success-message').length) {
                        $('#event-approval-status-container').prepend(
                            '<div id="approval-success-message" style="background: #d4edda; color: #155724; padding: 10px; margin-bottom: 10px; border: 1px solid #c3e6cb; border-radius: 4px;">Calendar approved successfully!</div>'
                        );
                        
                        setTimeout(function() {
                            $('#approval-success-message').fadeOut();
                        }, 5000);
                    }

                } else {
                    $button.prop('disabled', false).text(originalButtonText);
                    var errorMessage = (response && response.data) ? response.data : 'Unknown error occurred';
                    alert('Error: ' + errorMessage);
                }
            })
            .fail(function (xhr, status, error) {
                $button.prop('disabled', false).text(originalButtonText);
                
                var errorMessage = 'An error occurred. Please try again.';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied.';
                } else if (xhr.status === 404) {
                    errorMessage = 'Service not found.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                }
                
                alert(errorMessage);
            });
            
            return false;
        });
    });

})(jQuery);