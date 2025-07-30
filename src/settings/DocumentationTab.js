/**
 * WordPress dependencies
 */
import { Card, CardHeader, CardBody, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { marked } from 'marked';

/**
 * Documentation Tab Component
 */
const DocumentationTab = () => {
	const [ content, setContent ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	// Configure marked options for better security and rendering
	marked.setOptions( {
		breaks: true,
		gfm: true,
		sanitize: false, // We control the markdown content
	} );

	useEffect( () => {
		// Load the markdown documentation
		const loadDocumentation = async () => {
			try {
				setIsLoading( true );
				setError( null );

				// Fetch the markdown file from the plugin directory
				const response = await fetch(
					`${ window.wordpressMcpSettings.pluginUrl }/docs/client-setup.md`
				);

				if ( ! response.ok ) {
					throw new Error(
						`HTTP error! status: ${ response.status }`
					);
				}

				const markdownText = await response.text();
				
				// Replace placeholder with actual site URL
				const siteUrl = window.wordpressMcpSettings.siteUrl;
				const processedMarkdown = markdownText.replace(
					/\{\{your-website\.com\}\}/g,
					siteUrl
				);
				
				const htmlContent = marked( processedMarkdown );
				setContent( htmlContent );
			} catch ( err ) {
				console.error( 'Error loading documentation:', err );
				setError( err.message );
			} finally {
				setIsLoading( false );
			}
		};

		loadDocumentation();
	}, [] );

	if ( isLoading ) {
		return (
			<Card>
				<CardBody>
					<div className="documentation-loading">
						<Spinner />
						<p>
							{ __(
								'Loading documentation...',
								'wordpress-mcp'
							) }
						</p>
					</div>
				</CardBody>
			</Card>
		);
	}

	if ( error ) {
		return (
			<Card>
				<CardHeader>
					<h2>{ __( 'Documentation', 'wordpress-mcp' ) }</h2>
				</CardHeader>
				<CardBody>
					<div className="documentation-error">
						<p>
							{ __(
								'Error loading documentation:',
								'wordpress-mcp'
							) }{ ' ' }
							{ error }
						</p>
						<p>
							{ __(
								'Please check that the documentation file exists and is accessible.',
								'wordpress-mcp'
							) }
						</p>
					</div>
				</CardBody>
			</Card>
		);
	}

	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'MCP Client Setup Guide', 'wordpress-mcp' ) }</h2>
			</CardHeader>
			<CardBody>
				<div
					className="wordpress-mcp-documentation"
					dangerouslySetInnerHTML={ { __html: content } }
				/>
			</CardBody>
		</Card>
	);
};

export default DocumentationTab;
