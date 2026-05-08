(function () {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.subscribe) {
        return;
    }

    const { subscribe, select, dispatch } = wp.data;
    const FIELD = 'pdf_generation_notice';
    const NOTICE_ID = 'pdf-generation-notice';
    // We only surface errors; success/info states clear the notice instead.
    const SHOWN_TYPES = ['error', 'warning'];

    let lastSeenTimestamp = null;
    let wasSaving = false;
    let initialChecked = false;

    function syncNotice() {
        const editor = select('core/editor');
        if (!editor) return;

        const postId = editor.getCurrentPostId();
        if (!postId) return;

        const notice = editor.getEditedPostAttribute(FIELD);
        const hasNotice = notice && typeof notice === 'object' && notice.message
            && SHOWN_TYPES.indexOf(notice.type) !== -1;

        if (!hasNotice) {
            // No active notice -> make sure none is shown.
            dispatch('core/notices').removeNotice(NOTICE_ID);
            lastSeenTimestamp = null;
            return;
        }

        const ts = notice.time || 0;
        if (ts === lastSeenTimestamp) return;
        lastSeenTimestamp = ts;

        // Remove first to guarantee a single on-screen notice (createNotice
        // with an existing id is idempotent in some WP versions, not all).
        dispatch('core/notices').removeNotice(NOTICE_ID);
        dispatch('core/notices').createNotice(notice.type, notice.message, {
            isDismissible: true,
            id: NOTICE_ID,
        });

        wp.apiFetch({
            path: '/wp/v2/publications/' + postId,
            method: 'POST',
            data: { [FIELD]: null },
        }).catch(function () { /* non-fatal */ });
    }

    subscribe(function () {
        const editor = select('core/editor');
        if (!editor || !editor.getCurrentPostId()) return;

        const isSaving = editor.isSavingPost() && !editor.isAutosavingPost();
        const justFinishedSaving = wasSaving && !isSaving;
        wasSaving = isSaving;

        if (!initialChecked) {
            initialChecked = true;
            syncNotice();
            return;
        }

        if (justFinishedSaving) {
            syncNotice();
        }
    });
})();
