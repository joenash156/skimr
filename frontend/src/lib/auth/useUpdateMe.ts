import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ME_QUERY_KEY, updateMe, type Me, type UpdateMeBody } from './me';

/** PATCH /me, then put the returned user straight into the shared /me cache. */
export function useUpdateMe() {
  const queryClient = useQueryClient();
  return useMutation<Me, Error, UpdateMeBody>({
    mutationFn: updateMe,
    onSuccess: (me) => {
      queryClient.setQueryData(ME_QUERY_KEY, me);
    },
  });
}
