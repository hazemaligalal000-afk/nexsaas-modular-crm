const express = require('express');
const mjml2html = require('mjml');

const app = express();
app.use(express.json({ limit: '2mb' }));

/**
 * MJML Compiler Service
 * POST /compile
 * Body: { mjml: '...', options: { minify: true } }
 */
app.post('/compile', (req, res) => {
    const { mjml, options = {} } = req.body;

    if (!mjml) {
        return res.status(400).json({ error: 'mjml source required' });
    }

    try {
        const result = mjml2html(mjml, {
            beautify: false,
            minify: true,
            validationLevel: 'soft',
            ...options
        });

        if (result.errors && result.errors.length > 0) {
            return res.status(422).json({
                success: false,
                errors: result.errors,
                html: result.html // Return HTML even with errors
            });
        }

        return res.json({
            success: true,
            html: result.html,
            errors: []
        });

    } catch (err) {
        return res.status(500).json({ error: err.message });
    }
});

const PORT = process.env.PORT || 3001;
app.listen(PORT, () => console.log(`MJML compilation service running on port ${PORT}`));
