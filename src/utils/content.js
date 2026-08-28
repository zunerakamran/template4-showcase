export const listFrom = (data, key, fallback = []) => {
  const list = data?.[key];
  return Array.isArray(list) && list.length ? list : fallback;
};

export const splitLines = (value) => {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string') return value.split('\n');
  return [];
};
