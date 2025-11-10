/* face.js
   Provides simple helpers to enroll a face and verify a live face against a stored descriptor.
   Requires face-api.js and model files located at /CHPCEBU-Attendance/assets/models/
*/

const FACE = (function(){
    const MODEL_URL = '/CHPCEBU-Attendance/assets/models';

    async function loadModels(statusEl) {
        if (statusEl) statusEl.innerText = 'Loading face models...';
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        if (statusEl) statusEl.innerText = 'Models loaded.';
    }

    async function startVideo(videoEl) {
        const video = document.querySelector(videoEl);
        if (!video) return;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            await video.play();
        } catch (err) {
            console.error('Camera error', err);
            throw err;
        }
    }

    async function captureDescriptorFromVideo(videoEl) {
        const video = document.querySelector(videoEl);
        if (!video) throw new Error('Video element not found');
        const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
        if (!detection) return null;
        return Array.from(detection.descriptor);
    }

    async function postDescriptor(userId, descriptor) {
        const res = await fetch('/CHPCEBU-Attendance/save_face_descriptor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, descriptor })
        });
        return res.json();
    }

    // Public: initialize enrollment UI
    async function initEnrollment(userId, videoSelector, statusSelector, btnSelector, resultSelector) {
        const statusEl = document.querySelector(statusSelector);
        const resultEl = document.querySelector(resultSelector);
        const btn = document.querySelector(btnSelector);
        try {
            await loadModels(statusEl);
            await startVideo(videoSelector);
        } catch (err) {
            statusEl.innerText = 'Unable to start camera or load models: ' + err.message;
            return;
        }
        btn.addEventListener('click', async function(){
            statusEl.innerText = 'Detecting face...';
            const desc = await captureDescriptorFromVideo(videoSelector);
            if (!desc) {
                statusEl.innerText = 'No face detected. Try again.';
                return;
            }
            statusEl.innerText = 'Face detected, saving...';
            const r = await postDescriptor(userId, desc);
            if (r.ok) {
                statusEl.innerText = 'Enrollment saved.';
                resultEl.innerText = 'Descriptor length: ' + desc.length;
            } else {
                statusEl.innerText = 'Save failed: ' + (r.error || 'unknown');
            }
        });
    }

    // Public: verify current user's live face matches stored descriptor (threshold default 0.6)
    async function verifyCurrentUser(videoSelector, statusSelector, onSuccess, onFail, threshold = 0.6) {
        const statusEl = document.querySelector(statusSelector);
        try {
            await loadModels(statusEl);
            await startVideo(videoSelector);
        } catch (err) {
            statusEl.innerText = 'Camera/models error: ' + err.message;
            return;
        }

        statusEl.innerText = 'Fetching stored descriptor...';
        const resp = await fetch('/CHPCEBU-Attendance/get_face_descriptor.php');
        const data = await resp.json();
        if (!data.descriptor) {
            statusEl.innerText = 'No enrolled face descriptor found for your account.';
            if (onFail) onFail('no_descriptor');
            return;
        }
        statusEl.innerText = 'Detecting face...';
        const live = await captureDescriptorFromVideo(videoSelector);
        if (!live) {
            statusEl.innerText = 'No face detected.';
            if (onFail) onFail('no_face');
            return;
        }

        // compute Euclidean distance
        let sum = 0;
        for (let i=0;i<live.length;i++) {
            const d = live[i] - data.descriptor[i];
            sum += d*d;
        }
        const dist = Math.sqrt(sum);
        statusEl.innerText = 'Distance: ' + dist.toFixed(4);
        if (dist <= threshold) {
            if (onSuccess) onSuccess();
        } else {
            if (onFail) onFail('mismatch');
        }
    }

    return { initEnrollment, verifyCurrentUser };
})();
