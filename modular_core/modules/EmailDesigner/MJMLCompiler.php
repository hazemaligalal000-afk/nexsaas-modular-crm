<?php

namespace ModularCore\Modules\EmailDesigner;

/**
 * MJML Compiler Adapter - Email Designer (Batch EMAIL-A)
 * 
 * Responsibilities:
 * 1. Convert JSON design (from React) to MJML markup
 * 2. Inject Brand settings into the MJML structure
 * 3. Call Node.js MJML service to compile to responsive HTML
 */
class MJMLCompiler {
    const SERVICE_URL = 'http://localhost:3001/compile';
    private $http;

    public function __construct($http) {
        $this->http = $http;
    }

    /**
     * Compile MJML Body to Full HTML with Branding
     */
    public function compile(string $mjmlBody, array $brand, bool $injectBrand = true): string {
        if ($injectBrand) {
            $brandModel = new BrandSettingsModel($brand['db'] ?? null, $brand['s3'] ?? null);
            $head = $brandModel->toMJMLHeader($brand);
            $header = $brandModel->toMJMLHeaderBlock($brand);
            $footer = $brandModel->toMJMLFooterBlock($brand);

            $mjml = <<<MJML
<mjml>
  {$head}
  <mj-body background-color="{$brand['color_bg']}">
    {$header}
    {$mjmlBody}
    {$footer}
  </mj-body>
</mjml>
MJML;
        } else {
            $mjml = "<mjml><mj-head/><mj-body background-color=\"#f1f1f1\">{$mjmlBody}</mj-body></mjml>";
        }

        // Call Node.js MJML Service
        $response = $this->http->post(self::SERVICE_URL, [
            'mjml' => $mjml,
            'options' => ['minify' => true]
        ]);

        if (empty($response['success']) || empty($response['html'])) {
            throw new \Exception("MJML Compilation failed: " . json_encode($response['errors'] ?? 'Unknown Error'));
        }

        return $response['html'];
    }

    /**
     * Map JSON block structure from React Editor to MJML components
     */
    public function jsonToMJML(array $jsonDesign): string {
        $mjml = '';

        foreach ($jsonDesign['rows'] ?? [] as $row) {
            $columns = '';
            $colCount = count($row['columns'] ?? []);
            if ($colCount === 0) continue;
            
            $colWidth = (int)(100 / $colCount);

            foreach ($row['columns'] as $column) {
                $blocks = '';
                foreach ($column['blocks'] ?? [] as $block) {
                    $blocks .= $this->blockToMJML($block);
                }
                $columns .= "<mj-column width=\"{$colWidth}%\">{$blocks}</mj-column>";
            }

            $bgColor = $row['background_color'] ?? 'transparent';
            $padding = $row['padding'] ?? '0px 40px';
            $mjml .= "<mj-section background-color=\"{$bgColor}\" padding=\"{$padding}\">{$columns}</mj-section>";
        }

        return $mjml;
    }

    private function blockToMJML(array $block): string {
        return match($block['type']) {
            'text'    => $this->textBlock($block),
            'image'   => $this->imageBlock($block),
            'button'  => $this->buttonBlock($block),
            'divider' => "<mj-divider border-width=\"1px\" border-color=\"#e2e8f0\" padding=\"10px 0\"/>",
            'spacer'  => "<mj-spacer height=\"20px\"/>",
            default   => '',
        };
    }

    private function textBlock($b) {
        $align = $b['align'] ?? 'left';
        $size = $b['font_size'] ?? '16px';
        $color = $b['color'] ?? 'inherit';
        return "<mj-text align=\"{$align}\" font-size=\"{$size}\" color=\"{$color}\">{$b['content']}</mj-text>";
    }

    private function imageBlock($b) {
        $src = $b['url'] ?? '';
        $width = $b['width'] ?? '100%';
        return "<mj-image src=\"{$src}\" width=\"{$width}\" padding=\"0\" border-radius=\"8px\"/>";
    }

    private function buttonBlock($b) {
        return "<mj-button href=\"{$b['url']}\" background-color=\"{$b['color']}\">{$b['text']}</mj-button>";
    }
}
