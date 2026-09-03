const a = {account_name: 'test', short_name: 'test_short'};
const str = <option value=" + (a.short_name || a.account_name) + "> + a.account_name +   + (a.short_name ? () : '') + </option>;
console.log(str);
