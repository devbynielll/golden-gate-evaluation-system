// Reports have white paper backgrounds. Flatten transparency explicitly so PDF
// viewers never render the PNG's transparent black RGB pixels as a black box.
export function loadReportLogo(src, { ImageClass = Image, createCanvas = () => document.createElement('canvas') } = {}) {
    return new Promise((resolve, reject) => {
        const image = new ImageClass();
        image.onerror = () => reject(new Error('Could not load the report logo.'));
        image.onload = () => {
            try {
                const canvas = createCanvas();
                canvas.width = image.naturalWidth;
                canvas.height = image.naturalHeight;
                const context = canvas.getContext('2d');
                if (!context) throw new Error('Report image rendering is unavailable.');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(image, 0, 0);
                resolve(canvas.toDataURL('image/jpeg', 1));
            } catch (error) { reject(error); }
        };
        image.src = src;
    });
}
