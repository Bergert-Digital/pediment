import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';

type Attrs = { includePhone: boolean; recipientOverride: string; successMessage: string };

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps({ className: 'starter-contact-form' });
  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Form settings', 'starter')}>
          <ToggleControl label={__('Include phone field', 'starter')} checked={attributes.includePhone} onChange={(v) => setAttributes({ includePhone: v })} />
          <TextControl   label={__('Recipient email (override Brand default)', 'starter')} value={attributes.recipientOverride} onChange={(v) => setAttributes({ recipientOverride: v })} />
        </PanelBody>
      </InspectorControls>
      <form {...blockProps} onSubmit={(e) => e.preventDefault()}>
        <label>{__('Name', 'starter')} <input type="text" disabled placeholder={__('Name', 'starter')} /></label>
        <label>{__('Email', 'starter')} <input type="email" disabled placeholder={__('Email', 'starter')} /></label>
        {attributes.includePhone && <label>{__('Phone', 'starter')} <input type="tel" disabled placeholder={__('Phone', 'starter')} /></label>}
        <label>{__('Message', 'starter')} <textarea disabled placeholder={__('Message', 'starter')} /></label>
        <button type="button" disabled>{__('Send', 'starter')}</button>
        <RichText
          tagName="p"
          value={attributes.successMessage}
          onChange={(v) => setAttributes({ successMessage: v })}
          placeholder={__('Success message…', 'starter')}
        />
      </form>
    </>
  );
}
