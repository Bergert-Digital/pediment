import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls, MediaUpload } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, Button } from '@wordpress/components';

type Attrs = {
  variant: 'default' | 'split' | 'centered' | 'media-bg';
  headline: string;
  subheadline: string;
  ctaText: string;
  ctaUrl: string;
  mediaId: number;
};

export default function Edit({
  attributes,
  setAttributes,
}: {
  attributes: Attrs;
  setAttributes: (a: Partial<Attrs>) => void;
}) {
  const blockProps = useBlockProps({ className: `starter-hero is-variant-${attributes.variant}` });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Hero settings', 'starter')}>
          <SelectControl
            label={__('Variant', 'starter')}
            value={attributes.variant}
            options={[
              { label: 'Default',    value: 'default' },
              { label: 'Split',      value: 'split' },
              { label: 'Centered',   value: 'centered' },
              { label: 'Media BG',   value: 'media-bg' },
            ]}
            onChange={(v) => setAttributes({ variant: v as Attrs['variant'] })}
          />
          <TextControl
            label={__('CTA URL', 'starter')}
            value={attributes.ctaUrl}
            onChange={(v) => setAttributes({ ctaUrl: v })}
          />
          {attributes.variant === 'media-bg' && (
            <MediaUpload
              allowedTypes={['image']}
              onSelect={(media: any) => setAttributes({ mediaId: media.id })}
              render={({ open }: { open: () => void }) => (
                <Button variant="secondary" onClick={open}>
                  {attributes.mediaId ? __('Replace image', 'starter') : __('Pick image', 'starter')}
                </Button>
              )}
            />
          )}
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <RichText
          tagName="h1"
          value={attributes.headline}
          onChange={(v) => setAttributes({ headline: v })}
          placeholder={__('Headline…', 'starter')}
        />
        <RichText
          tagName="p"
          value={attributes.subheadline}
          onChange={(v) => setAttributes({ subheadline: v })}
          placeholder={__('Subheadline…', 'starter')}
        />
        <RichText
          tagName="span"
          value={attributes.ctaText}
          onChange={(v) => setAttributes({ ctaText: v })}
          placeholder={__('CTA text…', 'starter')}
        />
      </div>
    </>
  );
}
