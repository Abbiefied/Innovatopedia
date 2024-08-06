M.local_adapted = M.local_adapted || {};
M.local_adapted.courseid = null;
M.local_adapted.currentJobId = null;

M.local_adapted.init = function(Y, courseid) {
    M.local_adapted.courseid = courseid;
};

$(document).ready(function() {
    var conversionInProgress = false;

    $('#multimodal-form').on('submit', function(e) {
        e.preventDefault();
        if (conversionInProgress) {
            alert('A conversion is already in progress. Please wait.');
            return;
        }

        var formData = new FormData(this);
        formData.append('sesskey', M.cfg.sesskey);
        formData.append('courseid', M.cfg.courseid);

        $('#status-message').hide();
        $('#progress-container').show();
        $('#result-links').hide();
        $('#convert-btn').prop('disabled', true);
        conversionInProgress = true;

        $.ajax({
            url: M.cfg.wwwroot + '/local/adapted/multimodal/multimodal_generation.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                console.log("AJAX success. Response: ", response);
                if (response.success) {
                    $('#status-message').html('Converting file...').show();
                    M.local_adapted.currentJobId = response.job_id;
                    pollProgress(M.local_adapted.currentJobId);
                } else {
                    handleError(response.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("AJAX error: ", textStatus, errorThrown);
                console.log("Response text: ", jqXHR.responseText);
                handleError('An error occurred: ' + textStatus);
            }
        });
    });

    function pollProgress(jobId) {
        $.ajax({
            url: M.cfg.wwwroot + '/local/adapted/multimodal/multimodal_generation.php',
            type: 'POST',
            data: {
                check_progress: true,
                job_id: jobId,
                sesskey: M.cfg.sesskey,
                courseid: M.cfg.courseid
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                console.log("Poll progress response: ", response);
                if (response.success) {
                    $('#progress-bar').width(response.progress + '%').text(response.progress + '%');
                    
                    if (response.progress < 26) {
                        $('#progress-status').text('Generating audio...');
                    } else if (response.progress == 26) {
                        $('#progress-status').text('Generating slides... This may take several minutes.');
                    } else if (response.progress == 66) {
                        $('#progress-status').text('Generating video...');
                    }
            
                    if (response.progress < 100) {
                        setTimeout(function() { pollProgress(jobId); }, 10000); // Poll every 10 seconds
                    } else {
                        conversionComplete(response);
                    }
                } else {
                    handleError(response.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Poll progress error: ", textStatus, errorThrown);
                console.log("Response text: ", jqXHR.responseText);
                handleError('An error occurred while checking progress: ' + textStatus);
            }
        });
    }

    function conversionComplete(response) {
        console.log("Conversion complete. Response:", response);
        $('#progress-container').hide();
        $('#status-message').html('Conversion complete').show();
        $('#progress-status').text('');
        
        if (response.file_urls && response.file_urls.length > 0) {
            console.log("File URLs found:", response.file_urls);
            var fileList = '';
            response.file_urls.forEach(function(file) {
                fileList += '<li><a href="' + file.url + '" target="_blank">' + file.name + '</a></li>';
            });
            $('#result-files').html('<ul>' + fileList + '</ul>');
            $('#result-links').show();
        } else if (response.files && response.files.length > 0) {
            console.log("Files found, but no URLs:", response.files);
            $('#result-files').html('Generated files: ' + response.files.join(', '));
            $('#result-links').show();
            $('#download-btn').attr('data-files', response.files.join(','));
            $('#upload-btn').attr('data-job-id', M.local_adapted.currentJobId);
            $('#upload-btn').attr('data-files', response.files.join(','));
        } else {
            console.log("No files or URLs found in the response");
            $('#status-message').html('Conversion complete, but no files were generated.').show();
        }
        
        $('#convert-btn').prop('disabled', false);
        conversionInProgress = false;
    }

    function handleError(message) {
        $('#progress-container').hide();
        $('#status-message').html(message).show();
        $('#convert-btn').prop('disabled', false);
        conversionInProgress = false;
    }

    $('#upload-btn').on('click', function() {
        var jobId = $(this).attr('data-job-id');
        console.log('Button data-job-id:', jobId);
        console.log('Stored job ID:', M.local_adapted.currentJobId);
        console.log('Course ID:', M.local_adapted.courseid);
        
        jobId = jobId || M.local_adapted.currentJobId;
        var url = M.cfg.wwwroot + '/local/adapted/multimodal/upload_content.php';
        console.log('Uploading for job ID:', jobId);
        console.log('Course ID:', M.local_adapted.courseid);
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                job_id: jobId,
                courseid: M.local_adapted.courseid,
                sesskey: M.cfg.sesskey
            },
            dataType: 'json',
            success: function(response) {
                console.log('Success response:', response);
                if (response.success) {
                    $('#status-message').html(response.message).show();
                } else {
                    $('#status-message').html('Upload failed: ' + response.message).show();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Upload failed:', textStatus, errorThrown);
                console.log('Response status:', jqXHR.status);
                console.log('Response text:', jqXHR.responseText);
                try {
                    var errorObj = JSON.parse(jqXHR.responseText);
                    console.log('Parsed error object:', errorObj);
                } catch (e) {
                    console.log('Failed to parse error response as JSON');
                }
                $('#status-message').html('Upload failed: ' + textStatus + ' - ' + errorThrown).show();
            }
        });
    });
})