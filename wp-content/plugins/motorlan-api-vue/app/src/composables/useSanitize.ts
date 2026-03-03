import DOMPurify from 'dompurify';

export const useSanitize = () => {
	const sanitize = (dirty: string) => {
		return DOMPurify.sanitize(dirty);
	};

	return {
		sanitize,
	};
};
