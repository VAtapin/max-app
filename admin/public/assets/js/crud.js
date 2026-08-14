document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.crud-form');
    const title = document.querySelector('#broadcast-preview-title');
    const text = document.querySelector('#broadcast-preview-text');
    const media = document.querySelector('#broadcast-preview-media');
    if (!form || !title || !text || !media) return;
    const update = () => {
        title.textContent = form.elements.title?.value || 'Заголовок рассылки';
        text.textContent = form.elements.message_text?.value || 'Текст сообщения';
    };
    form.elements.title?.addEventListener('input', update);
    form.elements.message_text?.addEventListener('input', update);
    ['image_path', 'video_path'].forEach((name) => {
        form.elements[name]?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            if (!file) return;
            const url = URL.createObjectURL(file);
            media.innerHTML = name === 'video_path'
                ? `<video controls src="${url}"></video>`
                : `<img src="${url}" alt="">`;
        });
    });
});
