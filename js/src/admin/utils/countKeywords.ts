/**
 * Check if the keywords field is filled in correctly.
 */
export default function countKeywords(keywords: string | null | undefined): boolean {
  if (!keywords || keywords === '') return true;

  for (const keyword of keywords.split(',')) {
    // Keywords shouldn't have more than three spaces
    if (keyword.split(' ').length > 4) {
      return false;
    }
  }

  return true;
}
