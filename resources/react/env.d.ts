/// <reference types="vite/client" />

declare module '*.css' {
	const content: string;
	export default content;
}

declare module '@wordpress/i18n' {
	export function __( text: string, domain?: string ): string;
	export function _x( text: string, context: string, domain?: string ): string;
	export function _n( single: string, plural: string, number: number, domain?: string ): string;
	export function _nx( single: string, plural: string, number: number, context: string, domain?: string ): string;
	export function sprintf( format: string, ...args: unknown[] ): string;
}
